<?php

class DnepritNewsletterCampaignGetListProcessor extends modObjectGetListProcessor
{
    public $classKey = 'DnepritNewsletterCampaign';
    public $languageTopics = ['dnepritnewsletter:default'];
    public $defaultSortField = 'created_at';
    public $defaultSortDirection = 'DESC';
    public $objectType = 'dnepritnewsletter.campaign';

    public function beforeQuery()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_campaigns_view')) {
            return $this->modx->lexicon('access_denied');
        }

        return parent::beforeQuery();
    }

    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        $query = trim((string)$this->getProperty('query', ''));
        if ($query !== '') {
            $c->where([
                'title:LIKE' => '%' . $query . '%',
                'OR:subject:LIKE' => '%' . $query . '%',
                'OR:sender_email:LIKE' => '%' . $query . '%',
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
        $statusKey = 'dnepritnewsletter_campaign_status_' . $row['status'];
        $statusLabel = $this->modx->lexicon($statusKey);
        $row['status_label'] = $statusLabel === $statusKey ? $row['status'] : $statusLabel;
        $isDraft = $row['status'] === 'draft';
        $row['can_edit'] = $isDraft;
        $row['can_remove'] = true;
        $row['can_prepare'] = $isDraft && (int)$row['recipients_total'] === 0;

        return $row;
    }
}

return 'DnepritNewsletterCampaignGetListProcessor';
