<?php

class DnepritNewsletterQueueRetryProcessor extends modProcessor
{
    public $languageTopics = ['dnepritnewsletter:default', 'dnepritnewsletter:monitoring'];

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

        $transactionStarted = false;

        try {
            if (!$this->modx->beginTransaction()) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_queue_retry_err_transaction'));
            }
            $transactionStarted = true;

            $query = $this->modx->newQuery('DnepritNewsletterQueue');
            $query->where([
                'id:IN' => $ids,
                'status' => 'failed',
            ]);

            $items = $this->modx->getCollection('DnepritNewsletterQueue', $query);
            if (!$items) {
                throw new RuntimeException(
                    $this->modx->lexicon('dnepritnewsletter_queue_err_no_failed_selection')
                );
            }

            $campaignIds = [];
            $retried = 0;
            $now = date('Y-m-d H:i:s');

            /** @var DnepritNewsletterQueue $queue */
            foreach ($items as $queue) {
                $campaignId = (int)$queue->get('campaign_id');
                $campaignIds[$campaignId] = $campaignId;
                $previousAttempts = (int)$queue->get('attempts');

                $queue->set('status', 'pending');
                $queue->set('attempts', 0);
                $queue->set('last_error', null);
                $queue->set('next_attempt_at', $now);
                $queue->set('processing_at', null);
                $queue->set('sent_at', null);
                $queue->set('locked_at', null);
                $queue->set('locked_by', '');

                if (!$queue->save()) {
                    throw new RuntimeException(
                        $this->modx->lexicon('dnepritnewsletter_queue_retry_err_save', [
                            'email' => (string)$queue->get('email'),
                        ])
                    );
                }

                $this->writeLog($queue, $previousAttempts, $now);
                $retried++;
            }

            foreach ($campaignIds as $campaignId) {
                $this->refreshCampaign((int)$campaignId, $now);
            }

            if (!$this->modx->commit()) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_queue_retry_err_transaction'));
            }
            $transactionStarted = false;

            return $this->success(
                $this->modx->lexicon('dnepritnewsletter_queue_retry_success', ['count' => $retried]),
                ['retried' => $retried]
            );
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $this->modx->rollBack();
            }

            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[DnepritNewsletter] Manual retry failed: ' . $exception->getMessage()
            );

            return $this->failure($exception->getMessage());
        }
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

    protected function writeLog(xPDOObject $queue, $previousAttempts, $now)
    {
        /** @var DnepritNewsletterLog $log */
        $log = $this->modx->newObject('DnepritNewsletterLog');
        $log->fromArray([
            'campaign_id' => (int)$queue->get('campaign_id'),
            'subscriber_id' => (int)$queue->get('subscriber_id'),
            'queue_id' => (int)$queue->get('id'),
            'email' => (string)$queue->get('email'),
            'event' => 'manual_retry',
            'level' => 'info',
            'attempt' => (int)$previousAttempts,
            'message' => $this->modx->lexicon('dnepritnewsletter_queue_retry_log'),
            'created_at' => $now,
        ], '', true, true);

        if (!$log->save()) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_queue_retry_err_log'));
        }
    }

    protected function refreshCampaign($campaignId, $now)
    {
        /** @var DnepritNewsletterCampaign|null $campaign */
        $campaign = $this->modx->getObject('DnepritNewsletterCampaign', $campaignId);
        if (!$campaign) {
            return;
        }

        $failed = (int)$this->modx->getCount('DnepritNewsletterQueue', [
            'campaign_id' => $campaignId,
            'status' => 'failed',
        ]);
        $processing = (int)$this->modx->getCount('DnepritNewsletterQueue', [
            'campaign_id' => $campaignId,
            'status' => 'processing',
        ]);
        $pending = (int)$this->modx->getCount('DnepritNewsletterQueue', [
            'campaign_id' => $campaignId,
            'status' => 'pending',
        ]);

        $campaign->set('failed_count', $failed);
        $campaign->set('status', $processing > 0 ? 'sending' : 'queued');
        $campaign->set('finished_at', null);
        $campaign->set('updated_at', $now);

        if (($pending + $processing) > 0 && !$campaign->get('started_at')) {
            $campaign->set('started_at', $now);
        }

        if (!$campaign->save()) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_queue_retry_err_campaign'));
        }
    }
}

return 'DnepritNewsletterQueueRetryProcessor';
