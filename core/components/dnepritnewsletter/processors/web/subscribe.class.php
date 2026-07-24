<?php

class DnepritNewsletterWebSubscribeProcessor extends modProcessor
{
    public $languageTopics = ['dnepritnewsletter:web'];

    /** @var DnepritNewsletterPublicGuard */
    protected $guard;

    public function initialize()
    {
        $corePath = $this->modx->getOption(
            'dnepritnewsletter.core_path',
            null,
            $this->modx->getOption('core_path') . 'components/dnepritnewsletter/'
        );
        require_once $corePath . 'model/dnepritnewsletter/dnepritnewsletterpublicguard.class.php';
        $this->guard = new DnepritNewsletterPublicGuard($this->modx);

        return parent::initialize();
    }

    public function process()
    {
        if (!$this->guard->isSameOrigin()) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_web_err_origin'));
        }

        $formToken = trim((string)$this->getProperty('form_token', ''));
        $minimumAge = max(0, (int)$this->modx->getOption('dnepritnewsletter.subscribe_min_seconds', null, 2));
        $maximumAge = max(300, (int)$this->modx->getOption('dnepritnewsletter.subscribe_token_ttl', null, 7200));
        $inspection = $this->guard->inspect($formToken, 'subscribe', $minimumAge, $maximumAge);

        if (!$inspection['valid']) {
            return $this->failure($this->tokenErrorMessage((string)$inspection['reason']));
        }

        $metadata = $inspection['metadata'];
        if (trim((string)$this->getProperty('website', '')) !== '') {
            return $this->completeSilently($formToken, $metadata);
        }

        $ip = $this->guard->getClientIp();
        $ipLimit = max(0, (int)$this->modx->getOption('dnepritnewsletter.subscribe_ip_limit', null, 10));
        $ipWindow = max(60, (int)$this->modx->getOption('dnepritnewsletter.subscribe_ip_window', null, 600));
        if (!$this->guard->allow('subscribe-ip:' . $ip, $ipLimit, $ipWindow)) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_web_err_rate_limit'));
        }

        $email = $this->normalizeEmail((string)$this->getProperty('email', ''));
        $name = $this->normalizeName((string)$this->getProperty('name', ''));
        $consentRequired = !empty($metadata['require_consent']);
        $consent = (string)$this->getProperty('consent', '') === '1';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFieldError('email', $this->modx->lexicon('dnepritnewsletter_web_err_email'));
        }
        if (!empty($metadata['require_name']) && $name === '') {
            $this->addFieldError('name', $this->modx->lexicon('dnepritnewsletter_web_err_name'));
        }
        if ($consentRequired && !$consent) {
            $this->addFieldError('consent', $this->modx->lexicon('dnepritnewsletter_web_err_consent'));
        }
        if ($this->hasErrors()) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_web_err_validation'));
        }

        $emailLimit = max(0, (int)$this->modx->getOption('dnepritnewsletter.subscribe_email_limit', null, 3));
        $emailWindow = max(300, (int)$this->modx->getOption('dnepritnewsletter.subscribe_email_window', null, 3600));
        if (!$this->guard->allow('subscribe-email:' . $email, $emailLimit, $emailWindow)) {
            return $this->failure($this->modx->lexicon('dnepritnewsletter_web_err_rate_limit'));
        }

        $source = $this->normalizeSource((string)($metadata['source'] ?? 'website'));
        $now = date('Y-m-d H:i:s');
        /** @var DnepritNewsletterSubscriber|null $subscriber */
        $subscriber = $this->modx->getObject('DnepritNewsletterSubscriber', ['email' => $email]);
        $event = 'public_subscribe_existing';

        if (!$subscriber) {
            /** @var DnepritNewsletterSubscriber $subscriber */
            $subscriber = $this->modx->newObject('DnepritNewsletterSubscriber');
            $subscriber->fromArray([
                'email' => $email,
                'name' => $name,
                'status' => 'active',
                'source' => $source,
                'unsubscribe_token' => $this->makeSubscriberToken(),
                'failure_count' => 0,
                'blocked_reason' => '',
                'comment' => null,
                'subscribed_at' => $now,
                'updated_at' => $now,
                'unsubscribed_at' => null,
                'created_by' => 0,
            ], '', true, true);
            $event = 'public_subscribe_created';
        } else {
            $status = (string)$subscriber->get('status');
            if ($status === 'unsubscribed' && (bool)$this->modx->getOption('dnepritnewsletter.reactivate_unsubscribed', null, true)) {
                $subscriber->set('status', 'active');
                $subscriber->set('source', $source);
                $subscriber->set('unsubscribe_token', $this->makeSubscriberToken());
                $subscriber->set('failure_count', 0);
                $subscriber->set('blocked_reason', '');
                $subscriber->set('subscribed_at', $now);
                $subscriber->set('updated_at', $now);
                $subscriber->set('unsubscribed_at', null);
                if ($name !== '') {
                    $subscriber->set('name', $name);
                }
                $event = 'public_subscribe_reactivated';
            } elseif ($status === 'active') {
                $event = 'public_subscribe_existing';
            } else {
                return $this->completeSilently($formToken, $metadata);
            }
        }

        if (!$subscriber->save()) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[DnepritNewsletter] Could not save public subscriber: ' . $email);
            return $this->failure($this->modx->lexicon('dnepritnewsletter_web_err_save'));
        }

        $this->writeLog($subscriber, $event, $now);
        return $this->completeSilently($formToken, $metadata);
    }

    protected function completeSilently($formToken, array $metadata)
    {
        $this->guard->consume($formToken);
        $newToken = $this->guard->issue($metadata);

        return $this->success(
            $this->modx->lexicon('dnepritnewsletter_web_success'),
            ['form_token' => $newToken]
        );
    }

    protected function tokenErrorMessage($reason)
    {
        if ($reason === 'too_fast') {
            return $this->modx->lexicon('dnepritnewsletter_web_err_too_fast');
        }
        if ($reason === 'expired') {
            return $this->modx->lexicon('dnepritnewsletter_web_err_expired');
        }

        return $this->modx->lexicon('dnepritnewsletter_web_err_token');
    }

    protected function normalizeEmail($email)
    {
        $email = trim((string)$email);
        return function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);
    }

    protected function normalizeName($name)
    {
        $name = trim(strip_tags((string)$name));
        $name = preg_replace('/\s+/u', ' ', $name);
        return function_exists('mb_substr') ? mb_substr($name, 0, 255, 'UTF-8') : substr($name, 0, 255);
    }

    protected function normalizeSource($source)
    {
        $source = strtolower(trim((string)$source));
        $source = preg_replace('/[^a-z0-9_.-]+/', '-', $source);
        $source = trim($source, '-');
        if ($source === '') {
            $source = 'website';
        }

        return substr($source, 0, 100);
    }

    protected function makeSubscriberToken()
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Throwable $exception) {
            return hash('sha256', uniqid('', true) . microtime(true));
        }
    }

    protected function writeLog(xPDOObject $subscriber, $event, $now)
    {
        /** @var DnepritNewsletterLog $log */
        $log = $this->modx->newObject('DnepritNewsletterLog');
        $log->fromArray([
            'campaign_id' => null,
            'subscriber_id' => (int)$subscriber->get('id'),
            'queue_id' => null,
            'email' => (string)$subscriber->get('email'),
            'event' => (string)$event,
            'level' => 'info',
            'attempt' => 0,
            'message' => $this->modx->lexicon('dnepritnewsletter_web_log_' . $event),
            'created_at' => $now,
        ], '', true, true);

        if (!$log->save()) {
            $this->modx->log(modX::LOG_LEVEL_WARN, '[DnepritNewsletter] Could not save public subscription log.');
        }
    }
}

return 'DnepritNewsletterWebSubscribeProcessor';
