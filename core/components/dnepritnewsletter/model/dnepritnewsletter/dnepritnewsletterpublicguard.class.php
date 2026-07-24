<?php

class DnepritNewsletterPublicGuard
{
    /** @var modX */
    protected $modx;

    /** @var string */
    protected $sessionKey = 'dnepritnewsletter_public_forms';

    /** @var string */
    protected $rateLimitPath;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->rateLimitPath = rtrim((string)$modx->getOption('core_path'), '/\\')
            . '/cache/dnepritnewsletter/rate-limit/';
    }

    public function issue(array $metadata = [])
    {
        if (!$this->ensureSession()) {
            return '';
        }

        $this->pruneTokens();
        $token = $this->makeToken();
        $_SESSION[$this->sessionKey][$token] = [
            'created_at' => time(),
            'metadata' => $metadata,
        ];

        return $token;
    }

    public function inspect($token, $expectedAction, $minimumAge = 0, $maximumAge = 7200)
    {
        if (!$this->ensureSession()) {
            return ['valid' => false, 'reason' => 'session', 'metadata' => []];
        }

        $token = trim((string)$token);
        $record = isset($_SESSION[$this->sessionKey][$token])
            ? $_SESSION[$this->sessionKey][$token]
            : null;

        if (!is_array($record)) {
            return ['valid' => false, 'reason' => 'token', 'metadata' => []];
        }

        $createdAt = isset($record['created_at']) ? (int)$record['created_at'] : 0;
        $age = time() - $createdAt;
        $metadata = isset($record['metadata']) && is_array($record['metadata'])
            ? $record['metadata']
            : [];

        if ($createdAt <= 0 || $age < max(0, (int)$minimumAge)) {
            return ['valid' => false, 'reason' => 'too_fast', 'metadata' => $metadata];
        }

        if ($age > max(60, (int)$maximumAge)) {
            unset($_SESSION[$this->sessionKey][$token]);
            return ['valid' => false, 'reason' => 'expired', 'metadata' => $metadata];
        }

        if ((string)($metadata['action'] ?? '') !== (string)$expectedAction) {
            return ['valid' => false, 'reason' => 'action', 'metadata' => $metadata];
        }

        return ['valid' => true, 'reason' => '', 'metadata' => $metadata];
    }

    public function consume($token)
    {
        if (!$this->ensureSession()) {
            return;
        }

        unset($_SESSION[$this->sessionKey][trim((string)$token)]);
    }

    public function allow($key, $limit, $window)
    {
        $limit = max(0, (int)$limit);
        $window = max(1, (int)$window);
        if ($limit === 0) {
            return true;
        }

        if (!is_dir($this->rateLimitPath) && !@mkdir($this->rateLimitPath, 0775, true) && !is_dir($this->rateLimitPath)) {
            $this->modx->log(modX::LOG_LEVEL_WARN, '[DnepritNewsletter] Rate-limit directory is not writable.');
            return true;
        }

        $file = $this->rateLimitPath . hash('sha256', (string)$key) . '.json';
        $handle = @fopen($file, 'c+');
        if (!$handle) {
            $this->modx->log(modX::LOG_LEVEL_WARN, '[DnepritNewsletter] Could not open rate-limit file.');
            return true;
        }

        $allowed = true;
        if (flock($handle, LOCK_EX)) {
            rewind($handle);
            $contents = stream_get_contents($handle);
            $timestamps = json_decode((string)$contents, true);
            $timestamps = is_array($timestamps) ? $timestamps : [];
            $cutoff = time() - $window;
            $timestamps = array_values(array_filter($timestamps, static function ($timestamp) use ($cutoff) {
                return (int)$timestamp >= $cutoff;
            }));

            if (count($timestamps) >= $limit) {
                $allowed = false;
            } else {
                $timestamps[] = time();
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, json_encode($timestamps));
                fflush($handle);
            }

            flock($handle, LOCK_UN);
        }

        fclose($handle);
        return $allowed;
    }

    public function isSameOrigin()
    {
        $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin === '') {
            return true;
        }

        $originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
        if ($originHost === '') {
            return false;
        }

        $allowedHosts = [];
        $siteHost = strtolower((string)parse_url((string)$this->modx->getOption('site_url'), PHP_URL_HOST));
        if ($siteHost !== '') {
            $allowedHosts[] = $siteHost;
        }

        $httpHost = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
        if ($httpHost !== '') {
            $httpHost = preg_replace('/:\d+$/', '', $httpHost);
            $allowedHosts[] = $httpHost;
        }

        return in_array($originHost, array_unique($allowedHosts), true);
    }

    public function getClientIp()
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }

    protected function ensureSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        if (!isset($_SESSION[$this->sessionKey]) || !is_array($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [];
        }

        return true;
    }

    protected function pruneTokens()
    {
        $cutoff = time() - 7200;
        foreach ($_SESSION[$this->sessionKey] as $token => $record) {
            $createdAt = is_array($record) && isset($record['created_at']) ? (int)$record['created_at'] : 0;
            if ($createdAt < $cutoff) {
                unset($_SESSION[$this->sessionKey][$token]);
            }
        }

        if (count($_SESSION[$this->sessionKey]) > 30) {
            uasort($_SESSION[$this->sessionKey], static function ($left, $right) {
                return (int)($left['created_at'] ?? 0) <=> (int)($right['created_at'] ?? 0);
            });
            $_SESSION[$this->sessionKey] = array_slice($_SESSION[$this->sessionKey], -30, null, true);
        }
    }

    protected function makeToken()
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Throwable $exception) {
            return hash('sha256', uniqid('', true) . microtime(true));
        }
    }
}
