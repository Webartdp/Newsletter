<?php

class DnepritNewsletterCampaignSendBatchQueueWorker extends DnepritNewsletterQueueWorker
{
    /** @var int */
    protected $campaignId;

    public function __construct(modX $modx, $campaignId, DnepritNewsletterMailer $mailer = null, array $config = [])
    {
        $this->campaignId = (int)$campaignId;
        parent::__construct($modx, $mailer, $config);
    }

    protected function claim($limit)
    {
        $limit = max(1, min(20, (int)$limit));
        $queueTable = $this->modx->getTableName('DnepritNewsletterQueue');
        $campaignTable = $this->modx->getTableName('DnepritNewsletterCampaign');
        $now = date('Y-m-d H:i:s');
        $transactionStarted = false;

        try {
            if (!$this->modx->beginTransaction()) {
                throw new RuntimeException('Could not start queue claim transaction.');
            }
            $transactionStarted = true;

            $statement = $this->modx->prepare(
                'SELECT q.`id` FROM ' . $queueTable . ' AS q ' .
                'INNER JOIN ' . $campaignTable . ' AS c ON c.`id` = q.`campaign_id` ' .
                'WHERE q.`status` = ? ' .
                'AND q.`campaign_id` = ? ' .
                'AND c.`status` IN (\'queued\', \'scheduled\', \'sending\') ' .
                'AND (q.`next_attempt_at` IS NULL OR q.`next_attempt_at` <= ?) ' .
                'ORDER BY q.`next_attempt_at` ASC, q.`id` ASC ' .
                'LIMIT ' . $limit . ' FOR UPDATE'
            );
            $statement->execute(['pending', $this->campaignId, $now]);
            $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));

            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $params = array_merge(['processing', $now, $now, $this->workerId], $ids, ['pending']);
                $update = $this->modx->prepare(
                    'UPDATE ' . $queueTable . ' SET ' .
                    '`status` = ?, `processing_at` = ?, `locked_at` = ?, `locked_by` = ? ' .
                    'WHERE `id` IN (' . $placeholders . ') AND `status` = ?'
                );
                $update->execute($params);
            }

            if (!$this->modx->commit()) {
                throw new RuntimeException('Could not commit queue claim transaction.');
            }
            $transactionStarted = false;

            return $ids;
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $this->modx->rollBack();
            }
            throw $exception;
        }
    }
}

class DnepritNewsletterCampaignSendBatchProcessor extends modProcessor
{
    public $languageTopics = ['dnepritnewsletter:default', 'dnepritnewsletter:queue'];

    public function process()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_campaigns_manage')) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $campaignId = (int)$this->getProperty('id', 0);
        $limit = max(1, min(20, (int)$this->getProperty('limit', 5)));

        /** @var DnepritNewsletterCampaign|null $campaign */
        $campaign = $this->modx->getObject('DnepritNewsletterCampaign', $campaignId);
        if (!$campaign) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_campaign_err_not_found'));
        }

        if (!in_array((string)$campaign->get('status'), ['queued', 'scheduled', 'sending'], true)) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_queue_err_not_ready'));
        }

        $corePath = $this->modx->getOption(
            'dnepritnewsletter.core_path',
            null,
            MODX_CORE_PATH . 'components/dnepritnewsletter/'
        );

        require_once $corePath . 'model/dnepritnewsletter/dnepritnewslettermailer.class.php';
        require_once $corePath . 'model/dnepritnewsletter/dnepritnewsletterqueueworker.class.php';

        try {
            $worker = new DnepritNewsletterCampaignSendBatchQueueWorker($this->modx, $campaignId);
            $stats = $worker->run($limit);
        } catch (Throwable $exception) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[DnepritNewsletter] Browser sender failed: ' . $exception->getMessage()
            );
            return $this->failure($exception->getMessage());
        }

        $queueTable = $this->modx->getTableName('DnepritNewsletterQueue');
        $statement = $this->modx->prepare(
            'SELECT ' .
            'COUNT(*) AS `total`, ' .
            'COALESCE(SUM(`status` = \'pending\'), 0) AS `pending`, ' .
            'COALESCE(SUM(`status` = \'processing\'), 0) AS `processing`, ' .
            'COALESCE(SUM(`status` = \'sent\'), 0) AS `sent`, ' .
            'COALESCE(SUM(`status` = \'failed\'), 0) AS `failed`, ' .
            'COALESCE(SUM(`status` = \'skipped\'), 0) AS `skipped`, ' .
            'COALESCE(SUM(`status` = \'pending\' AND (`next_attempt_at` IS NULL OR `next_attempt_at` <= ?)), 0) AS `due` ' .
            'FROM ' . $queueTable . ' WHERE `campaign_id` = ?'
        );
        $statement->execute([date('Y-m-d H:i:s'), $campaignId]);
        $counts = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach (['total', 'pending', 'processing', 'sent', 'failed', 'skipped', 'due'] as $key) {
            $counts[$key] = isset($counts[$key]) ? (int)$counts[$key] : 0;
        }

        $remaining = $counts['pending'] + $counts['processing'];
        $complete = $remaining === 0;
        $processed = $counts['sent'] + $counts['failed'] + $counts['skipped'];
        $progress = $counts['total'] > 0
            ? round(($processed / $counts['total']) * 100, 2)
            : 100;
        $waiting = !$complete && (int)$stats['claimed'] === 0;

        return $this->success('', [
            'campaign_id' => $campaignId,
            'batch' => $stats,
            'total' => $counts['total'],
            'remaining' => $remaining,
            'pending' => $counts['pending'],
            'processing' => $counts['processing'],
            'sent' => $counts['sent'],
            'failed' => $counts['failed'],
            'skipped' => $counts['skipped'],
            'due' => $counts['due'],
            'progress' => $progress,
            'complete' => $complete,
            'waiting' => $waiting,
            'rate_limited' => !empty($stats['rate_limited']),
        ]);
    }
}

return 'DnepritNewsletterCampaignSendBatchProcessor';
