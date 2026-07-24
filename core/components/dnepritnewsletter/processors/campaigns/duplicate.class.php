<?php

class DnepritNewsletterCampaignDuplicateProcessor extends modProcessor
{
    public function process()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_campaigns_manage')) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $id = (int)$this->getProperty('id', 0);
        /** @var DnepritNewsletterCampaign|null $source */
        $source = $this->modx->getObject('DnepritNewsletterCampaign', $id);
        if (!$source) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_campaign_err_not_found'));
        }

        /** @var DnepritNewsletterCampaign $copy */
        $copy = $this->modx->newObject('DnepritNewsletterCampaign');
        $now = date('Y-m-d H:i:s');
        $copy->fromArray([
            'title' => trim((string)$source->get('title')) . ' — ' . $this->modx->lexicon('dnepritnewsletter_copy'),
            'subject' => (string)$source->get('subject'),
            'body_html' => (string)$source->get('body_html'),
            'body_text' => (string)$source->get('body_text'),
            'sender_email' => (string)$source->get('sender_email'),
            'sender_name' => (string)$source->get('sender_name'),
            'reply_to' => (string)$source->get('reply_to'),
            'status' => 'draft',
            'recipients_total' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'scheduled_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'created_by' => (int)$this->modx->user->get('id'),
        ], '', true, true);

        if (!$copy->save()) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_campaign_err_save'));
        }

        return $this->success($this->modx->lexicon('dnepritnewsletter_campaign_duplicated'), $copy);
    }
}

return 'DnepritNewsletterCampaignDuplicateProcessor';
