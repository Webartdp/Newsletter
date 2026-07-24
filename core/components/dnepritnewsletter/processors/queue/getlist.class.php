<?php

class DnepritNewsletterQueueGetListProcessor extends modObjectGetListProcessor
{
    public $classKey = 'DnepritNewsletterQueue';
    public $languageTopics = ['dnepritnewsletter:default', 'dnepritnewsletter:monitoring'];
    public $defaultSortField = 'id';
    public $defaultSortDirection = 'DESC';
    public $objectType = 'dnepritnewsletter.queue';

    /** @var array<int,string> */
    protected $campaignTitles = [];

    public function beforeQuery()
    {
        if (
            !$this->modx->user->sudo
            && !$this->modx->hasPermission('newsletter_queue_view')
            && !$this->modx->hasPermission('newsletter_view')
        ) {
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
                'OR:subject:LIKE' => '%' . $query . '%',
                'OR:last_error:LIKE' => '%' . $query . '%',
            ]);
        }

        $status = trim((string)$this->getProperty('status', ''));
        if ($status !== '') {
            $c->where(['status' => $status]);
        }

        $campaignId = (int)$this->getProperty('campaign_id', 0);
        if ($campaignId > 0) {
            $c->where(['campaign_id' => $campaignId]);
        }

        return $c;
    }

    public function prepareRow(xPDOObject $object)
    {
        $row = $object->toArray();
        $campaignId = (int)$row['campaign_id'];

        if (!array_key_exists($campaignId, $this->campaignTitles)) {
            /** @var DnepritNewsletterCampaign|null $campaign */
            $campaign = $this->modx->getObject('DnepritNewsletterCampaign', $campaignId);
            $this->campaignTitles[$campaignId] = $campaign ? (string)$campaign->get('title') : '';
        }

        $row['campaign_title'] = $this->campaignTitles[$campaignId];
        $statusKey = 'dnepritnewsletter_queue_status_' . $row['status'];
        $statusLabel = $this->modx->lexicon($statusKey);
        $row['status_label'] = $statusLabel === $statusKey ? $row['status'] : $statusLabel;
        $row['can_retry'] = $row['status'] === 'failed';
        $row['last_error_short'] = $this->shorten((string)$row['last_error'], 180);

        return $row;
    }

    protected function shorten($value, $length)
    {
        $value = trim((string)$value);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value, 'UTF-8') > $length
                ? mb_substr($value, 0, $length - 1, 'UTF-8') . '…'
                : $value;
        }

        return strlen($value) > $length
            ? substr($value, 0, $length - 3) . '...'
            : $value;
    }
}

return 'DnepritNewsletterQueueGetListProcessor';
