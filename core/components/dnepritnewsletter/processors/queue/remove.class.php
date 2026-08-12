<?php

class DnepritNewsletterQueueRemoveProcessor extends modProcessor
{
    public $languageTopics = [
        'dnepritnewsletter:default',
        'dnepritnewsletter:queue',
        'dnepritnewsletter:monitoring',
        'dnepritnewsletter:ui',
    ];

    public function process()
    {
        if (
            !$this->modx->user->sudo
            && !$this->modx->hasPermission('newsletter_queue_manage')
            && !$this->modx->hasPermission('newsletter_campaigns_manage')
        ) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $ids = $this->getIds();
        if (!$ids) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_queue_err_no_selection'));
        }

        $query = $this->modx->newQuery('DnepritNewsletterQueue');
        $query->where(['id:IN' => $ids]);
        $items = $this->modx->getCollection('DnepritNewsletterQueue', $query);
        if (!$items) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_queue_err_no_selection'));
        }

        $campaignIds = [];
        foreach ($items as $item) {
            if ((string)$item->get('status') === 'processing') {
                return $this->failure($this->modx->lexicon('dnepritnewsletter_queue_err_remove_processing'));
            }
            $campaignId = (int)$item->get('campaign_id');
            $campaignIds[$campaignId] = $campaignId;
        }

        $transactionStarted = false;

        try {
            if (!$this->modx->beginTransaction()) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_queue_remove_err_transaction'));
            }
            $transactionStarted = true;

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $logsTable = $this->modx->getTableName('DnepritNewsletterLog');
            $queueTable = $this->modx->getTableName('DnepritNewsletterQueue');

            $deleteLogs = $this->modx->prepare(
                'DELETE FROM ' . $logsTable . ' WHERE `queue_id` IN (' . $placeholders . ')'
            );
            $deleteLogs->execute($ids);

            $deleteQueue = $this->modx->prepare(
                'DELETE FROM ' . $queueTable . ' WHERE `id` IN (' . $placeholders . ')'
            );
            $deleteQueue->execute($ids);
            $removed = (int)$deleteQueue->rowCount();

            $now = date('Y-m-d H:i:s');
            foreach ($campaignIds as $campaignId) {
                $this->refreshCampaign((int)$campaignId, $now);
            }

            if (!$this->modx->commit()) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_queue_remove_err_transaction'));
            }
            $transactionStarted = false;
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $this->modx->rollBack();
            }

            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[DnepritNewsletter] Queue removal failed: ' . $exception->getMessage()
            );

            return $this->failure($exception->getMessage());
        }

        return $this->success(
            $this->modx->lexicon('dnepritnewsletter_queue_remove_success', ['count' => $removed]),
            ['removed' => $removed]
        );
    }

    protected function getIds()
    {
        $ids = $this->getProperty('ids', []);
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : [];
        }

        $singleId = (int)$this->getProperty('id', 0);
        if ($singleId > 0) {
            $ids[] = $singleId;
        }

        $ids = array_map('intval', (array)$ids);
        $ids = array_filter($ids, static function ($id) {
            return $id > 0;
        });

        return array_values(array_unique($ids));
    }

    protected function refreshCampaign($campaignId, $now)
    {
        /** @var DnepritNewsletterCampaign|null $campaign */
        $campaign = $this->modx->getObject('DnepritNewsletterCampaign', $campaignId);
        if (!$campaign) {
            return;
        }

        $total = (int)$this->modx->getCount('DnepritNewsletterQueue', ['campaign_id' => $campaignId]);
        $sent = (int)$this->modx->getCount('DnepritNewsletterQueue', [
            'campaign_id' => $campaignId,
            'status' => 'sent',
        ]);
        $failed = (int)$this->modx->getCount('DnepritNewsletterQueue', [
            'campaign_id' => $campaignId,
            'status' => 'failed',
        ]);
        $skipped = (int)$this->modx->getCount('DnepritNewsletterQueue', [
            'campaign_id' => $campaignId,
            'status' => 'skipped',
        ]);
        $pending = (int)$this->modx->getCount('DnepritNewsletterQueue', [
            'campaign_id' => $campaignId,
            'status' => 'pending',
        ]);
        $processing = (int)$this->modx->getCount('DnepritNewsletterQueue', [
            'campaign_id' => $campaignId,
            'status' => 'processing',
        ]);

        $campaign->set('recipients_total', $total);
        $campaign->set('sent_count', $sent);
        $campaign->set('failed_count', $failed);
        $campaign->set('skipped_count', $skipped);
        $campaign->set('updated_at', $now);

        if ($total === 0) {
            $campaign->set('status', 'draft');
            $campaign->set('scheduled_at', null);
            $campaign->set('started_at', null);
            $campaign->set('finished_at', null);
        } elseif ($processing > 0) {
            $campaign->set('status', 'sending');
            $campaign->set('finished_at', null);
        } elseif ($pending > 0) {
            $campaign->set(
                'status',
                (string)$campaign->get('status') === 'scheduled' ? 'scheduled' : 'queued'
            );
            $campaign->set('finished_at', null);
        } else {
            $campaign->set('status', $failed > 0 ? 'failed' : 'sent');
            $campaign->set('finished_at', $now);
        }

        if (!$campaign->save()) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_queue_remove_err_campaign'));
        }
    }
}

return 'DnepritNewsletterQueueRemoveProcessor';