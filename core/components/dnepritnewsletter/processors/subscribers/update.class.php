<?php

class DnepritNewsletterSubscriberUpdateProcessor extends modObjectUpdateProcessor
{
    public $classKey = 'DnepritNewsletterSubscriber';
    public $languageTopics = ['dnepritnewsletter:default'];
    public $objectType = 'dnepritnewsletter.subscriber';

    public function beforeSet()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_subscribers_manage')) {
            return $this->modx->lexicon('access_denied');
        }

        $email = strtolower(trim((string)$this->getProperty('email', '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFieldError('email', $this->modx->lexicon('dnepritnewsletter_err_email_invalid'));
        } else {
            $duplicate = $this->modx->newQuery($this->classKey);
            $duplicate->where([
                'email' => $email,
                'id:!=' => (int)$this->getProperty('id'),
            ]);
            if ($this->modx->getCount($this->classKey, $duplicate) > 0) {
                $this->addFieldError('email', $this->modx->lexicon('dnepritnewsletter_err_email_exists'));
            }
        }

        $status = (string)$this->getProperty('status', 'active');
        if (!in_array($status, ['active', 'unsubscribed', 'blocked'], true)) {
            $this->addFieldError('status', $this->modx->lexicon('dnepritnewsletter_err_status_invalid'));
        }

        if ($this->hasErrors()) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->setProperty('email', $email);
        $this->setProperty('name', trim((string)$this->getProperty('name', '')));
        $this->setProperty('status', $status);
        $this->setProperty('updated_at', $now);

        if ($status === 'active') {
            $this->setProperty('unsubscribed_at', null);
            $this->setProperty('blocked_reason', '');
            $this->setProperty('failure_count', 0);
        } elseif ($status === 'unsubscribed') {
            $this->setProperty('unsubscribed_at', $this->object->get('unsubscribed_at') ?: $now);
            $this->setProperty('blocked_reason', '');
        } elseif ($status === 'blocked' && !$this->getProperty('blocked_reason')) {
            $this->setProperty('blocked_reason', 'administrator');
        }

        return parent::beforeSet();
    }
}

return 'DnepritNewsletterSubscriberUpdateProcessor';
