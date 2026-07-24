<?php

class DnepritNewsletterQueueWorker
{
    /** @var modX */
    protected $modx;

    /** @var DnepritNewsletterMailer */
    protected $mailer;

    /** @var array */
    protected $config;

    /** @var string */
    protected $workerId;

    public function __construct(modX $modx, DnepritNewsletterMailer $mailer = null, array $config = [])
    {
        $this->modx = $modx;
        $this->mailer = $mailer ?: new DnepritNewsletterMailer($modx);
        $this->config = array_merge([
            'batch_size' => max(1, (int)$modx->getOption('dnepritnewsletter.batch_size', null, 50)),
            'limit_per_minute' => max(0, (int)$modx->getOption('dnepritnewsletter.limit_per_minute', null, 50)),
            'limit_per_hour' => max(0, (int)$modx->getOption('dnepritnewsletter.limit_per_hour', null, 500)),
            'max_attempts' => max(1, (int)$modx->getOption('dnepritnewsletter.max_attempts', null, 3)),
            'retry_delay' => max(60, (int)$modx->getOption('dnepritnewsletter.retry_delay', null, 300)),
            'lock_ttl' => max(300, (int)$modx->getOption('dnepritnewsletter.lock_ttl', null, 3600)),
        ], $config);
        $this->workerId = $this->makeWorkerId();
    }

    public function run($limit = null)
    {
        $stats = [
            'worker_id' => $this->workerId,
            'released_stale' => $this->releaseStaleLocks(),
            'claimed' => 0,
            'sent' => 0,
            'retried' => 0,
            'failed' => 0,
            'skipped' => 0,
            'rate_limited' => false,
        ];

        $available = $this->resolveAvailableLimit($limit);
        if ($available <= 0) {
            $stats['rate_limited'] = true;
            return $stats;
        }

        $ids = $this->claim($available);
        $stats['claimed'] = count($ids);
        $campaignIds = [];

        foreach ($ids as $id) {
            /** @var DnepritNewsletterQueue|null $queue */
            $queue = $this->modx->getObject('DnepritNewsletterQueue', [
                'id' => (int)$id,
                'status' => 'processing',
                'locked_by' => $this->workerId,
            ]);
            if (!$queue) {
                continue;
            }

            $campaignId = (int)$queue->get('campaign_id');
            $campaignIds[$campaignId] = $campaignId;

            if (!$this->isSubscriberActive((int)$queue->get('subscriber_id'))) {
                $this->markSkipped($queue, 'Subscriber is no longer active.');
                $stats['skipped']++;
                continue;
            }

            try {
                $this->mailer->send($queue->toArray());
                $this->markSent($queue);
                $stats['sent']++;
            } catch (Throwable $exception) {
                if ($this->markFailure($queue, $exception->getMessage())) {
                    $stats['failed']++;
                } else {
                    $stats['retried']++;
                }
            }
        }

        foreach ($campaignIds as $campaignId) {
            $this->updateCampaignStats($campaignId);
        }

        return $stats;
    }

    protected function resolveAvailableLimit($limit)
    {
        $batchSize = $limit === null
            ? (int)$this->config['batch_size']
            : max(1, min(1000, (int)$limit));

        $minuteLimit = (int)$this->config['limit_per_minute'];
        if ($minuteLimit > 0) {
            $sentLastMinute = $this->countSentSince(date('Y-m-d H:i:s', time() - 60));
            $batchSize = min($batchSize, max(0, $minuteLimit - $sentLastMinute));
        }

        $hourLimit = (int)$this->config['limit_per_hour'];
        if ($hourLimit > 0) {
            $sentLastHour = $this->countSentSince(date('Y-m-d H:i:s', time() - 3600));
            $batchSize = min($batchSize, max(0, $hourLimit - $sentLastHour));
        }

        return max(0, $batchSize);
    }

    protected function countSentSince($since)
    {
        $query = $this->modx->newQuery('DnepritNewsletterLog');
        $query->where([
            'event' => 'sent',
            'created_at:>=' => $since,
        ]);

        return (int)$this->modx->getCount('DnepritNewsletterLog', $query);
    }

    protected function claim($limit)
    {
        $limit = max(1, min(1000, (int)$limit));
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
                'AND c.`status` IN (\'queued\', \'scheduled\', \'sending\') ' .
                'AND (q.`next_attempt_at` IS NULL OR q.`next_attempt_at` <= ?) ' .
                'ORDER BY q.`next_attempt_at` ASC, q.`id` ASC ' .
                'LIMIT ' . $limit . ' FOR UPDATE'
            );
            $statement->execute(['pending', $now]);
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

    protected function releaseStaleLocks()
    {
        $queueTable = $this->modx->getTableName('DnepritNewsletterQueue');
        $cutoff = date('Y-m-d H:i:s', time() - (int)$this->config['lock_ttl']);
        $now = date('Y-m-d H:i:s');
        $statement = $this->modx->prepare(
            'UPDATE ' . $queueTable . ' SET ' .
            '`status` = \'pending\', `processing_at` = NULL, `locked_at` = NULL, `locked_by` = \'\', ' .
            '`next_attempt_at` = ? ' .
            'WHERE `status` = \'processing\' AND `locked_at` IS NOT NULL AND `locked_at` < ?'
        );
        $statement->execute([$now, $cutoff]);

        return (int)$statement->rowCount();
    }

    protected function isSubscriberActive($subscriberId)
    {
        /** @var DnepritNewsletterSubscriber|null $subscriber */
        $subscriber = $this->modx->getObject('DnepritNewsletterSubscriber', $subscriberId);
        return $subscriber && (string)$subscriber->get('status') === 'active';
    }

    protected function markSent(xPDOObject $queue)
    {
        $attempt = (int)$queue->get('attempts') + 1;
        $now = date('Y-m-d H:i:s');
        $queue->set('status', 'sent');
        $queue->set('attempts', $attempt);
        $queue->set('last_error', null);
        $queue->set('next_attempt_at', null);
        $queue->set('sent_at', $now);
        $queue->set('locked_at', null);
        $queue->set('locked_by', '');

        if (!$queue->save()) {
            throw new RuntimeException('Message was sent, but the queue record could not be updated.');
        }

        $this->writeLog($queue, 'sent', 'info', $attempt, 'Message sent successfully.');
    }

    protected function markSkipped(xPDOObject $queue, $reason)
    {
        $queue->set('status', 'skipped');
        $queue->set('last_error', $this->cleanError($reason));
        $queue->set('next_attempt_at', null);
        $queue->set('locked_at', null);
        $queue->set('locked_by', '');

        if (!$queue->save()) {
            throw new RuntimeException('Could not mark inactive recipient as skipped.');
        }

        $this->writeLog($queue, 'skipped_inactive', 'info', (int)$queue->get('attempts'), $reason);
    }

    protected function markFailure(xPDOObject $queue, $error)
    {
        $attempt = (int)$queue->get('attempts') + 1;
        $final = $attempt >= (int)$this->config['max_attempts'];
        $cleanError = $this->cleanError($error);

        $queue->set('attempts', $attempt);
        $queue->set('last_error', $cleanError);
        $queue->set('locked_at', null);
        $queue->set('locked_by', '');

        if ($final) {
            $queue->set('status', 'failed');
            $queue->set('next_attempt_at', null);
        } else {
            $queue->set('status', 'pending');
            $queue->set(
                'next_attempt_at',
                date('Y-m-d H:i:s', time() + $this->calculateRetryDelay($attempt))
            );
        }

        if (!$queue->save()) {
            throw new RuntimeException('Could not update failed queue record. Original error: ' . $cleanError);
        }

        $this->writeLog(
            $queue,
            $final ? 'failed' : 'retry_scheduled',
            $final ? 'error' : 'warning',
            $attempt,
            $cleanError
        );

        return $final;
    }

    protected function calculateRetryDelay($attempt)
    {
        $multiplier = pow(2, max(0, (int)$attempt - 1));
        return min(86400, (int)$this->config['retry_delay'] * $multiplier);
    }

    protected function updateCampaignStats($campaignId)
    {
        /** @var DnepritNewsletterCampaign|null $campaign */
        $campaign = $this->modx->getObject('DnepritNewsletterCampaign', (int)$campaignId);
        if (!$campaign) {
            return;
        }

        $sent = (int)$this->modx->getCount('DnepritNewsletterQueue', [
            'campaign_id' => (int)$campaignId,
            'status' => 'sent',
        ]);
        $failed = (int)$this->modx->getCount('DnepritNewsletterQueue', [
            'campaign_id' => (int)$campaignId,
            'status' => 'failed',
        ]);
        $skipped = (int)$this->modx->getCount('DnepritNewsletterQueue', [
            'campaign_id' => (int)$campaignId,
            'status' => 'skipped',
        ]);
        $open = (int)$this->modx->getCount('DnepritNewsletterQueue', [
            'campaign_id' => (int)$campaignId,
            'status:IN' => ['pending', 'processing'],
        ]);
        $now = date('Y-m-d H:i:s');

        $campaign->set('sent_count', $sent);
        $campaign->set('failed_count', $failed);
        $campaign->set('skipped_count', $skipped);
        $campaign->set('updated_at', $now);

        if (!$campaign->get('started_at') && ($sent + $failed + $skipped + $open) > 0) {
            $campaign->set('started_at', $now);
        }

        if ($open === 0) {
            $campaign->set('status', $failed > 0 ? 'failed' : 'sent');
            $campaign->set('finished_at', $now);
        } elseif ((string)$campaign->get('status') !== 'paused') {
            $campaign->set('status', 'sending');
            $campaign->set('finished_at', null);
        }

        if (!$campaign->save()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[DnepritNewsletter] Could not update campaign statistics for campaign ' . (int)$campaignId
            );
        }
    }

    protected function writeLog(xPDOObject $queue, $event, $level, $attempt, $message)
    {
        /** @var DnepritNewsletterLog $log */
        $log = $this->modx->newObject('DnepritNewsletterLog');
        $log->fromArray([
            'campaign_id' => (int)$queue->get('campaign_id'),
            'subscriber_id' => (int)$queue->get('subscriber_id'),
            'queue_id' => (int)$queue->get('id'),
            'email' => (string)$queue->get('email'),
            'event' => (string)$event,
            'level' => (string)$level,
            'attempt' => (int)$attempt,
            'message' => $this->cleanError($message),
            'created_at' => date('Y-m-d H:i:s'),
        ], '', true, true);

        if (!$log->save()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[DnepritNewsletter] Could not save delivery log for queue item ' . (int)$queue->get('id')
            );
        }
    }

    protected function cleanError($message)
    {
        $message = trim((string)$message);
        if (function_exists('mb_substr')) {
            return mb_substr($message, 0, 4000, 'UTF-8');
        }

        return substr($message, 0, 4000);
    }

    protected function makeWorkerId()
    {
        $host = function_exists('gethostname') ? (string)gethostname() : 'host';
        $pid = function_exists('getmypid') ? (int)getmypid() : 0;

        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (Throwable $exception) {
            $suffix = substr(sha1(uniqid('', true)), 0, 8);
        }

        return substr($host . ':' . $pid . ':' . $suffix, 0, 100);
    }
}
