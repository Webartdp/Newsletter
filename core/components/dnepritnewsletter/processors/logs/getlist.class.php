<?php

class DnepritNewsletterLogGetListProcessor extends modObjectGetListProcessor
{
    public $classKey = 'DnepritNewsletterLog';
    public $languageTopics = ['dnepritnewsletter:default', 'dnepritnewsletter:monitoring'];
    public $defaultSortField = 'created_at';
    public $defaultSortDirection = 'DESC';
    public $objectType = 'dnepritnewsletter.log';

    /** @var array<int,string> */
    protected $campaignTitles = [];

    public function beforeQuery()
    {
        if (
            !$this->modx->user->sudo
            && !$this->modx->hasPermission('newsletter_logs_view')
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
                'OR:message:LIKE' => '%' . $query . '%',
                'OR:event:LIKE' => '%' . $query . '%',
            ]);
        }

        $event = trim((string)$this->getProperty('event', ''));
        if ($event !== '') {
            $c->where(['event' => $event]);
        }

        $level = trim((string)$this->getProperty('level', ''));
        if ($level !== '') {
            $c->where(['level' => $level]);
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

        if ($campaignId > 0 && !array_key_exists($campaignId, $this->campaignTitles)) {
            /** @var DnepritNewsletterCampaign|null $campaign */
            $campaign = $this->modx->getObject('DnepritNewsletterCampaign', $campaignId);
            $this->campaignTitles[$campaignId] = $campaign ? (string)$campaign->get('title') : '';
        }

        $row['campaign_title'] = $campaignId > 0
            ? (string)($this->campaignTitles[$campaignId] ?? '')
            : '';

        $eventKey = 'dnepritnewsletter_log_event_' . $row['event'];
        $eventLabel = $this->modx->lexicon($eventKey);
        $row['event_label'] = $eventLabel === $eventKey ? $row['event'] : $eventLabel;

        $levelKey = 'dnepritnewsletter_log_level_' . $row['level'];
        $levelLabel = $this->modx->lexicon($levelKey);
        $row['level_label'] = $levelLabel === $levelKey ? $row['level'] : $levelLabel;
        $row['message_short'] = $this->shorten((string)$row['message'], 220);

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

return 'DnepritNewsletterLogGetListProcessor';
