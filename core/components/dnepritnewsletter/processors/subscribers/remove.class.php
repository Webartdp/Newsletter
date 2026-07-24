<?php

class DnepritNewsletterSubscriberRemoveProcessor extends modObjectRemoveProcessor
{
    public $classKey = 'DnepritNewsletterSubscriber';
    public $languageTopics = ['dnepritnewsletter:default'];
    public $objectType = 'dnepritnewsletter.subscriber';

    public function beforeRemove()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_subscribers_manage')) {
            return $this->modx->lexicon('access_denied');
        }

        return parent::beforeRemove();
    }
}

return 'DnepritNewsletterSubscriberRemoveProcessor';
