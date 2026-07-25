<?php

class DnepritNewsletterSettingsUpdateProcessor extends modProcessor
{
    public $languageTopics = ['dnepritnewsletter:default'];

    public function process()
    {
        if (!$this->canManageSettings()) {
            return $this->failure($this->modx->lexicon('access_denied'));
        }

        $senderEmail = trim((string)$this->getProperty('sender_email', ''));
        $replyTo = trim((string)$this->getProperty('reply_to', ''));

        if ($senderEmail !== '' && !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFieldError('sender_email', $this->modx->lexicon('dnepritnewsletter_settings_err_sender_email'));
        }
        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $this->addFieldError('reply_to', $this->modx->lexicon('dnepritnewsletter_settings_err_reply_to'));
        }

        $values = [
            'sender_email' => $senderEmail,
            'sender_name' => trim((string)$this->getProperty('sender_name', '')),
            'reply_to' => $replyTo,
            'batch_size' => $this->integer('batch_size', 1, 1000, 50),
            'limit_per_minute' => $this->integer('limit_per_minute', 0, 10000, 50),
            'limit_per_hour' => $this->integer('limit_per_hour', 0, 1000000, 500),
            'max_attempts' => $this->integer('max_attempts', 1, 20, 3),
            'retry_delay' => $this->integer('retry_delay', 60, 86400, 300),
            'lock_ttl' => $this->integer('lock_ttl', 300, 86400, 3600),
            'failure_limit' => $this->integer('failure_limit', 1, 100, 3),
            'unsubscribe_resource_id' => $this->integer('unsubscribe_resource_id', 0, PHP_INT_MAX, 0),
            'import_max_size' => $this->integer('import_max_size_mb', 1, 100, 10) * 1048576,
            'require_consent' => $this->boolean('require_consent'),
            'reactivate_unsubscribed' => $this->boolean('reactivate_unsubscribed'),
            'subscribe_min_seconds' => $this->integer('subscribe_min_seconds', 0, 3600, 2),
            'subscribe_token_ttl' => $this->integer('subscribe_token_ttl', 300, 604800, 7200),
            'subscribe_ip_limit' => $this->integer('subscribe_ip_limit', 0, 10000, 10),
            'subscribe_ip_window' => $this->integer('subscribe_ip_window', 60, 86400, 600),
            'subscribe_email_limit' => $this->integer('subscribe_email_limit', 0, 10000, 3),
            'subscribe_email_window' => $this->integer('subscribe_email_window', 60, 86400, 3600),
        ];

        if ($this->hasErrors()) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_settings_err_validation'));
        }

        $transactionStarted = false;

        try {
            if (!$this->modx->beginTransaction()) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_settings_err_save'));
            }
            $transactionStarted = true;

            foreach ($values as $name => $value) {
                $this->saveSetting('dnepritnewsletter.' . $name, $value);
            }

            if (!$this->modx->commit()) {
                throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_settings_err_save'));
            }
            $transactionStarted = false;
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $this->modx->rollBack();
            }

            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[DnepritNewsletter] Settings update failed: ' . $exception->getMessage()
            );

            return $this->failure($exception->getMessage());
        }

        $cacheManager = $this->modx->getCacheManager();
        if ($cacheManager) {
            $cacheManager->refresh([
                'system_settings' => [],
                'context_settings' => ['contexts' => ['mgr']],
            ]);
        }

        return $this->success($this->modx->lexicon('dnepritnewsletter_settings_saved'));
    }

    protected function integer($name, $minimum, $maximum, $default)
    {
        $raw = $this->getProperty($name, $default);
        $value = filter_var($raw, FILTER_VALIDATE_INT);

        if ($value === false || $value < $minimum || $value > $maximum) {
            $this->addFieldError(
                $name,
                $this->modx->lexicon('dnepritnewsletter_settings_err_number', [
                    'min' => $minimum,
                    'max' => $maximum,
                ])
            );
            return $default;
        }

        return (int)$value;
    }

    protected function boolean($name)
    {
        return (int)((bool)$this->getProperty($name, false));
    }

    protected function saveSetting($key, $value)
    {
        /** @var modSystemSetting|null $setting */
        $setting = $this->modx->getObject('modSystemSetting', ['key' => $key]);
        if (!$setting) {
            $setting = $this->modx->newObject('modSystemSetting');
            $setting->set('key', $key);
            $setting->set('namespace', 'dnepritnewsletter');
            $setting->set('area', 'dnepritnewsletter_main');
        }

        $setting->set('value', (string)$value);
        if (!$setting->save()) {
            throw new RuntimeException($this->modx->lexicon('dnepritnewsletter_settings_err_save'));
        }
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

return 'DnepritNewsletterSettingsUpdateProcessor';
