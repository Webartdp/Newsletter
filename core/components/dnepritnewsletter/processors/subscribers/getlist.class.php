<?php

class DnepritNewsletterSubscriberGetListProcessor extends modObjectGetListProcessor
{
    public $classKey = 'DnepritNewsletterSubscriber';
    public $languageTopics = ['dnepritnewsletter:default'];
    public $defaultSortField = 'id';
    public $defaultSortDirection = 'DESC';
    public $objectType = 'dnepritnewsletter.subscriber';

    public function beforeQuery()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_subscribers_view')) {
            return $this->modx->lexicon('access_denied');
        }

        return parent::beforeQuery();
    }

    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        $query = trim((string)$this->getProperty('query', ''));
        if ($query !== '') {
            $c->where([
                'email:LIKE' => '%' . $query . '%',
                'OR:name:LIKE' => '%' . $query . '%',
            ]);
        }

        $status = trim((string)$this->getProperty('status', ''));
        if ($status !== '') {
            $c->where(['status' => $status]);
        }

        return $c;
    }

    public function prepareRow(xPDOObject $object)
    {
        $row = $object->toArray();
        $row['status_label'] = $this->modx->lexicon('dnepritnewsletter_subscriber_status_' . $row['status']);
        return $row;
    }
}

return 'DnepritNewsletterSubscriberGetListProcessor';
