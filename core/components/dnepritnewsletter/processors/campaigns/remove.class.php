<?php

class DnepritNewsletterCampaignRemoveProcessor extends modObjectRemoveProcessor
{
    public $classKey = 'DnepritNewsletterCampaign';
    public $languageTopics = ['dnepritnewsletter:default'];
    public $objectType = 'dnepritnewsletter.campaign';

    public function beforeRemove()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_campaigns_manage')) {
            return $this->modx->lexicon('access_denied');
        }

        if ((string)$this->object->get('status') !== 'draft') {
            return $this->modx->lexicon('dnepritnewsletter_campaign_err_locked');
        }

        if ($this->modx->getCount('DnepritNewsletterQueue', ['campaign_id' => (int)$this->object->get('id')]) > 0) {
            return $this->modx->lexicon('dnepritnewsletter_campaign_err_has_queue');
        }

        return parent::beforeRemove();
    }
}

return 'DnepritNewsletterCampaignRemoveProcessor';
