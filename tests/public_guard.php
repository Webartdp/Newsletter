<?php

declare(strict_types=1);

class modX
{
    public const LOG_LEVEL_WARN = 2;

    private $options;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function getOption($key, $options = null, $default = null)
    {
        return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
    }

    public function log($level, $message)
    {
    }
}

function expectTrue($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

function removeTree($path)
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $target = $path . DIRECTORY_SEPARATOR . $item;
        is_dir($target) ? removeTree($target) : unlink($target);
    }
    rmdir($path);
}

$temp = sys_get_temp_dir() . '/dnepritnewsletter-guard-' . bin2hex(random_bytes(4)) . '/';
mkdir($temp, 0775, true);
session_id('dnepritnewsletter-test-' . bin2hex(random_bytes(4)));
session_start();

$_SERVER['HTTP_HOST'] = 'example.test';
$_SERVER['HTTP_ORIGIN'] = 'https://example.test';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$modx = new modX([
    'core_path' => $temp,
    'site_url' => 'https://example.test/',
]);

require_once __DIR__ . '/../core/components/dnepritnewsletter/model/dnepritnewsletter/dnepritnewsletterpublicguard.class.php';

$guard = new DnepritNewsletterPublicGuard($modx);
$token = $guard->issue(['action' => 'subscribe', 'source' => 'test']);
expectTrue($token !== '', 'Token was not created.');

$tooFast = $guard->inspect($token, 'subscribe', 2, 7200);
expectTrue($tooFast['valid'] === false && $tooFast['reason'] === 'too_fast', 'Minimum form age was not enforced.');

$valid = $guard->inspect($token, 'subscribe', 0, 7200);
expectTrue($valid['valid'] === true, 'Valid token was rejected.');
expectTrue(($valid['metadata']['source'] ?? '') === 'test', 'Token metadata was not preserved.');

$wrongAction = $guard->inspect($token, 'unsubscribe', 0, 7200);
expectTrue($wrongAction['valid'] === false && $wrongAction['reason'] === 'action', 'Action binding was not enforced.');

expectTrue($guard->isSameOrigin() === true, 'Same origin was rejected.');
$_SERVER['HTTP_ORIGIN'] = 'https://attacker.test';
expectTrue($guard->isSameOrigin() === false, 'Foreign origin was accepted.');

expectTrue($guard->allow('ip:127.0.0.1', 2, 60) === true, 'First rate-limit hit was rejected.');
expectTrue($guard->allow('ip:127.0.0.1', 2, 60) === true, 'Second rate-limit hit was rejected.');
expectTrue($guard->allow('ip:127.0.0.1', 2, 60) === false, 'Rate limit was not enforced.');

$guard->consume($token);
$consumed = $guard->inspect($token, 'subscribe', 0, 7200);
expectTrue($consumed['valid'] === false && $consumed['reason'] === 'token', 'Consumed token remained valid.');

session_write_close();
removeTree($temp);

echo "Public guard tests passed.\n";
