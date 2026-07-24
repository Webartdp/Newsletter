<?php

class DnepritNewsletterSubscriberCreateProcessor extends modObjectCreateProcessor
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
        } elseif ($this->modx->getCount($this->classKey, ['email' => $email]) > 0) {
            $this->addFieldError('email', $this->modx->lexicon('dnepritnewsletter_err_email_exists'));
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
        $this->setProperty('source', 'manager');
        $this->setProperty('unsubscribe_token', bin2hex(random_bytes(32)));
        $this->setProperty('failure_count', 0);
        $this->setProperty('blocked_reason', $status === 'blocked' ? 'administrator' : '');
        $this->setProperty('subscribed_at', $now);
        $this->setProperty('updated_at', $now);
        $this->setProperty('unsubscribed_at', $status === 'unsubscribed' ? $now : null);
        $this->setProperty('created_by', (int)$this->modx->user->get('id'));

        return parent::beforeSet();
    }
}

return 'DnepritNewsletterSubscriberCreateProcessor';
