<?php

class DnepritNewsletterCampaignRemoveProcessor extends modProcessor
{
    public $languageTopics = [
        'dnepritnewsletter:default',
        'dnepritnewsletter:monitoring',
        'dnepritnewsletter:ui',
    ];

    public function process()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_campaigns_manage')) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $id = (int)$this->getProperty('id', 0);
        /** @var DnepritNewsletterCampaign|null $campaign */
        $campaign = $this->modx->getObject('DnepritNewsletterCampaign', $id);
        if (!$campaign) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_campaign_err_not_found'));
        }

        if ($this->modx->getCount('DnepritNewsletterQueue', [
            'campaign_id' => $id,
            'status' => 'processing',
        ]) > 0) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_campaign_err_remove_sending'));
        }

        $transactionStarted = false;

        try {
            if (!$this->modx->beginTransaction()) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_campaign_err_remove_transaction'));
            }
            $transactionStarted = true;

            $logsTable = $this->modx->getTableName('DnepritNewsletterLog');
            $queueTable = $this->modx->getTableName('DnepritNewsletterQueue');

            $deleteLogs = $this->modx->prepare('DELETE FROM ' . $logsTable . ' WHERE `campaign_id` = ?');
            $deleteLogs->execute([$id]);

            $deleteQueue = $this->modx->prepare('DELETE FROM ' . $queueTable . ' WHERE `campaign_id` = ?');
            $deleteQueue->execute([$id]);

            if (!$campaign->remove()) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_campaign_err_remove'));
            }

            if (!$this->modx->commit()) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_campaign_err_remove_transaction'));
            }
            $transactionStarted = false;
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $this->modx->rollBack();
            }

            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[DnepritNewsletter] Campaign removal failed: ' . $exception->getMessage()
            );

            return $this->failure($exception->getMessage());
        }

        return $this->success($this->modx->lexicon('dnepritnewsletter_campaign_removed'));
    }
}

return 'DnepritNewsletterCampaignRemoveProcessor';
