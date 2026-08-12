<?php

class DnepritNewsletterPublicGuard
{
    /** @var modX */
    protected $modx;

    /** @var string */
    protected $rateLimitPath;

    /** @var string */
    protected $tokenPath;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;

        $cachePath = rtrim((string)$modx->getOption('core_path'), '/\\')
            . '/cache/dnepritnewsletter/';

        $this->rateLimitPath = $cachePath . 'rate-limit/';
        $this->tokenPath = $cachePath . 'form-tokens/';
    }

    public function issue(array $metadata = [])
    {
        if (!$this->ensureDirectory($this->tokenPath)) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[DnepritNewsletter] Form-token directory is not writable.'
            );

            return '';
        }

        $this->pruneTokens();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $token = $this->makeToken();
            $file = $this->getTokenFile($token);

            if (is_file($file)) {
                continue;
            }

            $payload = [
                'created_at' => time(),
                'metadata' => $metadata,
            ];

            if ($this->writeJsonFileExclusive($file, $payload)) {
                return $token;
            }
        }

        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            '[DnepritNewsletter] Could not create public form token.'
        );

        return '';
    }

    public function inspect($token, $expectedAction, $minimumAge = 0, $maximumAge = 7200)
    {
        $token = trim((string)$token);

        if (!$this->isValidTokenFormat($token)) {
            return [
                'valid' => false,
                'reason' => 'token',
                'metadata' => [],
            ];
        }

        $file = $this->getTokenFile($token);

        if (!is_file($file) || !is_readable($file)) {
            return [
                'valid' => false,
                'reason' => 'token',
                'metadata' => [],
            ];
        }

        $record = $this->readJsonFile($file);

        if (!is_array($record)) {
            @unlink($file);

            return [
                'valid' => false,
                'reason' => 'token',
                'metadata' => [],
            ];
        }

        $createdAt = isset($record['created_at'])
            ? (int)$record['created_at']
            : 0;

        $metadata = isset($record['metadata']) && is_array($record['metadata'])
            ? $record['metadata']
            : [];

        $age = time() - $createdAt;

        if ($createdAt <= 0) {
            @unlink($file);

            return [
                'valid' => false,
                'reason' => 'token',
                'metadata' => $metadata,
            ];
        }

        if ($age < max(0, (int)$minimumAge)) {
            return [
                'valid' => false,
                'reason' => 'too_fast',
                'metadata' => $metadata,
            ];
        }

        if ($age > max(60, (int)$maximumAge)) {
            @unlink($file);

            return [
                'valid' => false,
                'reason' => 'expired',
                'metadata' => $metadata,
            ];
        }

        if ((string)($metadata['action'] ?? '') !== (string)$expectedAction) {
            return [
                'valid' => false,
                'reason' => 'action',
                'metadata' => $metadata,
            ];
        }

        return [
            'valid' => true,
            'reason' => '',
            'metadata' => $metadata,
        ];
    }

    public function consume($token)
    {
        $token = trim((string)$token);

        if (!$this->isValidTokenFormat($token)) {
            return;
        }

        $file = $this->getTokenFile($token);

        if (is_file($file)) {
            @unlink($file);
        }
    }

    public function allow($key, $limit, $window)
    {
        $limit = max(0, (int)$limit);
        $window = max(1, (int)$window);

        if ($limit === 0) {
            return true;
        }

        if (!$this->ensureDirectory($this->rateLimitPath)) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                '[DnepritNewsletter] Rate-limit directory is not writable.'
            );

            return true;
        }

        $file = $this->rateLimitPath
            . hash('sha256', (string)$key)
            . '.json';

        $handle = @fopen($file, 'c+');

        if (!$handle) {
            $this->modx->log(
                modX::LOG_LEVEL_WARN,
                '[DnepritNewsletter] Could not open rate-limit file.'
            );

            return true;
        }

        $allowed = true;

        if (flock($handle, LOCK_EX)) {
            rewind($handle);

            $contents = stream_get_contents($handle);
            $timestamps = json_decode((string)$contents, true);
            $timestamps = is_array($timestamps)
                ? $timestamps
                : [];

            $cutoff = time() - $window;

            $timestamps = array_values(
                array_filter(
                    $timestamps,
                    static function ($timestamp) use ($cutoff) {
                        return (int)$timestamp >= $cutoff;
                    }
                )
            );

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

        $originHost = strtolower(
            (string)parse_url($origin, PHP_URL_HOST)
        );

        if ($originHost === '') {
            return false;
        }

        $allowedHosts = [];

        $siteHost = strtolower(
            (string)parse_url(
                (string)$this->modx->getOption('site_url'),
                PHP_URL_HOST
            )
        );

        if ($siteHost !== '') {
            $allowedHosts[] = $siteHost;
        }

        $httpHost = strtolower(
            trim((string)($_SERVER['HTTP_HOST'] ?? ''))
        );

        if ($httpHost !== '') {
            $httpHost = preg_replace('/:\\d+$/', '', $httpHost);
            $allowedHosts[] = $httpHost;
        }

        return in_array(
            $originHost,
            array_unique($allowedHosts),
            true
        );
    }

    public function getClientIp()
    {
        $ip = trim(
            (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );

        return filter_var($ip, FILTER_VALIDATE_IP)
            ? $ip
            : 'unknown';
    }

    protected function ensureDirectory($path)
    {
        if (is_dir($path)) {
            return is_writable($path);
        }

        if (!@mkdir($path, 0775, true) && !is_dir($path)) {
            return false;
        }

        return is_writable($path);
    }

    protected function pruneTokens()
    {
        if (!is_dir($this->tokenPath)) {
            return;
        }

        $files = glob($this->tokenPath . '*.json');

        if (!is_array($files)) {
            return;
        }

        $cutoff = time() - 7200;

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $record = $this->readJsonFile($file);

            if (!is_array($record)) {
                @unlink($file);
                continue;
            }

            $createdAt = isset($record['created_at'])
                ? (int)$record['created_at']
                : 0;

            if ($createdAt <= 0 || $createdAt < $cutoff) {
                @unlink($file);
            }
        }
    }

    protected function getTokenFile($token)
    {
        return $this->tokenPath . $token . '.json';
    }

    protected function isValidTokenFormat($token)
    {
        return (bool)preg_match('/^[a-f0-9]{64}$/i', (string)$token);
    }

    protected function readJsonFile($file)
    {
        $contents = @file_get_contents($file);

        if ($contents === false || $contents === '') {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded)
            ? $decoded
            : null;
    }

    protected function writeJsonFileExclusive($file, array $payload)
    {
        $handle = @fopen($file, 'x');

        if (!$handle) {
            return false;
        }

        $json = json_encode($payload);
        $written = false;

        if (is_string($json)) {
            $written = fwrite($handle, $json) !== false;
            fflush($handle);
        }

        fclose($handle);

        if (!$written) {
            @unlink($file);
        }

        return $written;
    }

    protected function makeToken()
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Throwable $exception) {
            return hash(
                'sha256',
                uniqid('', true) . microtime(true)
            );
        }
    }
}
