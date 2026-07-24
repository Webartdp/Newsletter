<?php

class DnepritNewsletterCampaignPrepareQueueProcessor extends modProcessor
{
    public $languageTopics = ['dnepritnewsletter:default', 'dnepritnewsletter:queue'];

    public function process()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_campaigns_manage')) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $campaignId = (int)$this->getProperty('id', 0);
        if ($campaignId <= 0) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_campaign_err_not_found'));
        }

        try {
            $scheduledAt = $this->resolveScheduledAt();
        } catch (InvalidArgumentException $exception) {
            return $this->failure($exception->getMessage());
        }

        $rendererPath = $this->modx->getOption(
            'dnepritnewsletter.core_path',
            null,
            MODX_CORE_PATH . 'components/dnepritnewsletter/'
        ) . 'model/dnepritnewsletter/dnepritnewsletterrenderer.class.php';
        require_once $rendererPath;
        $renderer = new DnepritNewsletterRenderer($this->modx);

        $transactionStarted = false;

        try {
            if (!$this->modx->beginTransaction()) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_queue_err_transaction'));
            }
            $transactionStarted = true;

            $campaignTable = $this->modx->getTableName('DnepritNewsletterCampaign');
            $lockStatement = $this->modx->prepare(
                'SELECT `id` FROM ' . $campaignTable . ' WHERE `id` = ? FOR UPDATE'
            );
            $lockStatement->execute([$campaignId]);

            if (!$lockStatement->fetchColumn()) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_campaign_err_not_found'));
            }

            /** @var DnepritNewsletterCampaign|null $campaign */
            $campaign = $this->modx->getObject('DnepritNewsletterCampaign', $campaignId);
            if (!$campaign) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_campaign_err_not_found'));
            }

            if ((string)$campaign->get('status') !== 'draft') {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_queue_err_already_prepared'));
            }

            if ($this->modx->getCount('DnepritNewsletterQueue', ['campaign_id' => $campaignId]) > 0) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_queue_err_already_exists'));
            }

            $subscriberCount = $this->modx->getCount(
                'DnepritNewsletterSubscriber',
                ['status' => 'active']
            );
            if ($subscriberCount <= 0) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_queue_err_no_recipients'));
            }

            $campaignData = $campaign->toArray();
            $queuedAt = date('Y-m-d H:i:s');
            $created = 0;
            $query = $this->modx->newQuery('DnepritNewsletterSubscriber');
            $query->where(['status' => 'active']);
            $query->sortby('id', 'ASC');

            /** @var DnepritNewsletterSubscriber $subscriber */
            foreach ($this->modx->getIterator('DnepritNewsletterSubscriber', $query) as $subscriber) {
                $subscriberData = $subscriber->toArray();
                $rendered = $renderer->render($campaignData, $subscriberData);

                /** @var DnepritNewsletterQueue $queue */
                $queue = $this->modx->newObject('DnepritNewsletterQueue');
                $queue->fromArray([
                    'campaign_id' => $campaignId,
                    'subscriber_id' => (int)$subscriberData['id'],
                    'email' => (string)$subscriberData['email'],
                    'name' => (string)$subscriberData['name'],
                    'subject' => $rendered['subject'],
                    'body_html' => $rendered['body_html'],
                    'body_text' => $rendered['body_text'],
                    'sender_email' => (string)$campaignData['sender_email'],
                    'sender_name' => (string)$campaignData['sender_name'],
                    'reply_to' => (string)$campaignData['reply_to'],
                    'status' => 'pending',
                    'attempts' => 0,
                    'last_error' => null,
                    'queued_at' => $queuedAt,
                    'next_attempt_at' => $scheduledAt,
                    'processing_at' => null,
                    'sent_at' => null,
                    'locked_at' => null,
                    'locked_by' => '',
                ], '', true, true);

                if (!$queue->save()) {
                    throw new RuntimeException(
                        $this->modx->lexicon('dnepritnewsletter_queue_err_save', [
                            'email' => (string)$subscriberData['email'],
                        ])
                    );
                }

                $created++;
            }

            $isScheduled = strtotime($scheduledAt) > time() + 30;
            $campaign->set('status', $isScheduled ? 'scheduled' : 'queued');
            $campaign->set('scheduled_at', $scheduledAt);
            $campaign->set('recipients_total', $created);
            $campaign->set('sent_count', 0);
            $campaign->set('failed_count', 0);
            $campaign->set('updated_at', $queuedAt);

            if (!$campaign->save()) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_queue_err_campaign_save'));
            }

            /** @var DnepritNewsletterLog $log */
            $log = $this->modx->newObject('DnepritNewsletterLog');
            $log->fromArray([
                'campaign_id' => $campaignId,
                'subscriber_id' => null,
                'queue_id' => null,
                'email' => '',
                'event' => 'queue_prepared',
                'level' => 'info',
                'attempt' => 0,
                'message' => $this->modx->lexicon('dnepritnewsletter_queue_log_prepared', [
                    'count' => $created,
                    'date' => $scheduledAt,
                ]),
                'created_at' => $queuedAt,
            ], '', true, true);
            $log->save();

            if (!$this->modx->commit()) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_queue_err_transaction'));
            }
            $transactionStarted = false;

            return $this->success(
                $this->modx->lexicon('dnepritnewsletter_queue_prepared', ['count' => $created]),
                [
                    'created' => $created,
                    'scheduled_at' => $scheduledAt,
                    'status' => $isScheduled ? 'scheduled' : 'queued',
                ]
            );
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $this->modx->rollBack();
            }

            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[DnepritNewsletter] Queue preparation failed: ' . $exception->getMessage()
            );

            return $this->failure($exception->getMessage());
        }
    }

    protected function resolveScheduledAt()
    {
        $sendNow = (bool)$this->getProperty('send_now', true);
        if ($sendNow) {
            return date('Y-m-d H:i:s');
        }

        $date = trim((string)$this->getProperty('scheduled_date', ''));
        $time = trim((string)$this->getProperty('scheduled_time', ''));
        $timestamp = strtotime($date . ' ' . $time);

        if ($date === '' || $time === '' || $timestamp === false) {
            throw new InvalidArgumentException(
                $this->modx->lexicon('dnepritnewsletter_queue_err_schedule_invalid')
            );
        }

        if ($timestamp <= time() + 60) {
            throw new InvalidArgumentException(
                $this->modx->lexicon('dnepritnewsletter_queue_err_schedule_past')
            );
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}

return 'DnepritNewsletterCampaignPrepareQueueProcessor';
