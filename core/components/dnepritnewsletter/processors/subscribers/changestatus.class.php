<?php

class DnepritNewsletterSubscriberChangeStatusProcessor extends modProcessor
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

        $status = (string)$this->getProperty('status', '');
        if (!in_array($status, ['active', 'unsubscribed', 'blocked'], true)) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_err_status_invalid'));
        }

        $now = date('Y-m-d H:i:s');
        $count = 0;
        $objects = $this->modx->getIterator('DnepritNewsletterSubscriber', ['id:IN' => $ids]);

        foreach ($objects as $subscriber) {
            $subscriber->set('status', $status);
            $subscriber->set('updated_at', $now);

            if ($status === 'active') {
                $subscriber->set('unsubscribed_at', null);
                $subscriber->set('blocked_reason', '');
                $subscriber->set('failure_count', 0);
            } elseif ($status === 'unsubscribed') {
                $subscriber->set('unsubscribed_at', $subscriber->get('unsubscribed_at') ?: $now);
                $subscriber->set('blocked_reason', '');
            } else {
                $subscriber->set('blocked_reason', 'administrator');
            }

            if ($subscriber->save()) {
                $count++;
            }
        }

        return $this->success($this->modx->lexicon('dnepritnewsletter_status_changed'), ['updated' => $count]);
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

return 'DnepritNewsletterSubscriberChangeStatusProcessor';
