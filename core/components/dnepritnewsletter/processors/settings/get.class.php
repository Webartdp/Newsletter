<?php

class DnepritNewsletterSettingsGetProcessor extends modProcessor
{
    public $languageTopics = ['dnepritnewsletter:default'];

    public function process()
    {
        if (!$this->canManageSettings()) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $defaults = [
            'sender_email' => '',
            'sender_name' => '',
            'reply_to' => '',
            'batch_size' => 50,
            'limit_per_minute' => 50,
            'limit_per_hour' => 500,
            'max_attempts' => 3,
            'retry_delay' => 300,
            'lock_ttl' => 3600,
            'failure_limit' => 3,
            'unsubscribe_resource_id' => 0,
            'import_max_size' => 10485760,
            'require_consent' => 1,
            'reactivate_unsubscribed' => 1,
            'subscribe_min_seconds' => 2,
            'subscribe_token_ttl' => 7200,
            'subscribe_ip_limit' => 10,
            'subscribe_ip_window' => 600,
            'subscribe_email_limit' => 3,
            'subscribe_email_window' => 3600,
        ];

        $data = [];
        foreach ($defaults as $name => $default) {
            $value = $this->modx->getOption('dnepritnewsletter.' . $name, null, $default);
            $data[$name] = is_int($default) ? (int)$value : (string)$value;
        }

        $data['import_max_size_mb'] = max(1, round(((int)$data['import_max_size']) / 1048576, 2));
        unset($data['import_max_size']);

        $data['mail_use_smtp'] = (int)$this->modx->getOption('mail_use_smtp', null, 0);
        $data['mail_smtp_hosts'] = (string)$this->modx->getOption('mail_smtp_hosts', null, '');
        $data['mail_smtp_port'] = (int)$this->modx->getOption('mail_smtp_port', null, 0);
        $data['mail_smtp_user'] = (string)$this->modx->getOption('mail_smtp_user', null, '');

        return $this->success('', $data);
    }

    protected function canManageSettings()
    {
        return (bool)(
            $this->modx->user->sudo
            || $this->modx->hasPermission('newsletter_settings_manage')
            || $this->modx->hasPermission('settings')
        );
    }
}

return 'DnepritNewsletterSettingsGetProcessor';
