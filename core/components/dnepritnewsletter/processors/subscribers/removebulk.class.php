<?php

class DnepritNewsletterSubscriberRemoveBulkProcessor extends modProcessor
{
    public function process()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_subscribers_manage')) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $ids = $this->normalizeIds($this->getProperty('ids', []));
        if (!$ids) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_err_no_selection'));
        }

        $removed = $this->modx->removeCollection('DnepritNewsletterSubscriber', ['id:IN' => $ids]);
        if (!$removed) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_err_remove'));
        }

        return $this->success($this->modx->lexicon('dnepritnewsletter_subscribers_removed'));
    }

    private function normalizeIds($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $value))));
    }
}

return 'DnepritNewsletterSubscriberRemoveBulkProcessor';
