<?php

class DnepritNewsletterCampaignStatsProcessor extends modProcessor
{
    public $languageTopics = ['dnepritnewsletter:default', 'dnepritnewsletter:monitoring'];

    public function process()
    {
        if (
            !$this->modx->user->sudo
            && !$this->modx->hasPermission('newsletter_campaigns_view')
            && !$this->modx->hasPermission('newsletter_view')
        ) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $campaignId = (int)$this->getProperty('id', 0);
        /** @var DnepritNewsletterCampaign|null $campaign */
        $campaign = $this->modx->getObject('DnepritNewsletterCampaign', $campaignId);
        if (!$campaign) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_campaign_err_not_found'));
        }

        $counts = [];
        foreach (['pending', 'processing', 'sent', 'failed', 'skipped'] as $status) {
            $counts[$status] = (int)$this->modx->getCount('DnepritNewsletterQueue', [
                'campaign_id' => $campaignId,
                'status' => $status,
            ]);
        }

        $actualTotal = array_sum($counts);
        $total = max((int)$campaign->get('recipients_total'), $actualTotal);
        $completed = $counts['sent'] + $counts['failed'] + $counts['skipped'];
        $deliveries = $counts['sent'] + $counts['failed'];
        $retryEvents = (int)$this->modx->getCount('DnepritNewsletterLog', [
            'campaign_id' => $campaignId,
            'event:IN' => ['retry_scheduled', 'manual_retry'],
        ]);

        $status = (string)$campaign->get('status');
        $statusKey = 'dnepritnewsletter_campaign_status_' . $status;
        $statusLabel = $this->modx->lexicon($statusKey);

        return $this->success('', [
            'id' => $campaignId,
            'title' => (string)$campaign->get('title'),
            'subject' => (string)$campaign->get('subject'),
            'status' => $status,
            'status_label' => $statusLabel === $statusKey ? $status : $statusLabel,
            'total' => $total,
            'pending' => $counts['pending'],
            'processing' => $counts['processing'],
            'sent' => $counts['sent'],
            'failed' => $counts['failed'],
            'skipped' => $counts['skipped'],
            'completed' => $completed,
            'progress' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'delivery_rate' => $deliveries > 0 ? round(($counts['sent'] / $deliveries) * 100, 1) : 0,
            'retry_events' => $retryEvents,
            'scheduled_at' => $campaign->get('scheduled_at'),
            'started_at' => $campaign->get('started_at'),
            'finished_at' => $campaign->get('finished_at'),
        ]);
    }
}

return 'DnepritNewsletterCampaignStatsProcessor';
