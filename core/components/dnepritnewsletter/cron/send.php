<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$options = getopt('', ['limit::']);
$basePath = getenv('MODX_BASE_PATH');
if (!$basePath) {
    $basePath = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR;
}
$basePath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR;
$configCore = $basePath . 'config.core.php';

if (!is_file($configCore)) {
    fwrite(STDERR, "MODX config.core.php not found: {$configCore}\n");
    exit(1);
}

require_once $configCore;
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

$corePath = $modx->getOption(
    'dnepritnewsletter.core_path',
    null,
    MODX_CORE_PATH . 'components/dnepritnewsletter/'
);
require_once $corePath . 'model/dnepritnewsletter/dnepritnewsletter.class.php';
require_once $corePath . 'model/dnepritnewsletter/dnepritnewslettermailer.class.php';
require_once $corePath . 'model/dnepritnewsletter/dnepritnewsletterqueueworker.class.php';

new DnepritNewsletter($modx);

$lockDirectory = MODX_CORE_PATH . 'cache/dnepritnewsletter/';
if (!is_dir($lockDirectory) && !mkdir($lockDirectory, 0700, true) && !is_dir($lockDirectory)) {
    fwrite(STDERR, "Could not create Cron lock directory.\n");
    exit(1);
}

$lockHandle = fopen($lockDirectory . 'sender.lock', 'c+');
if (!$lockHandle) {
    fwrite(STDERR, "Could not open Cron lock file.\n");
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo json_encode([
        'success' => true,
        'already_running' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    fclose($lockHandle);
    exit(0);
}

ftruncate($lockHandle, 0);
fwrite($lockHandle, (string)getmypid());
fflush($lockHandle);

try {
    $limit = isset($options['limit']) && $options['limit'] !== false
        ? max(1, min(1000, (int)$options['limit']))
        : null;
    $worker = new DnepritNewsletterQueueWorker($modx);
    $stats = $worker->run($limit);

    echo json_encode([
        'success' => true,
        'stats' => $stats,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $exception) {
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        '[DnepritNewsletter] Cron sender failed: ' . $exception->getMessage()
    );
    fwrite(STDERR, json_encode([
        'success' => false,
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
exit(0);
