<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$configCore = DNEPRITNEWSLETTER_MODX_BASE_PATH . 'config.core.php';
if (!is_file($configCore)) {
    fwrite(STDERR, "MODX config.core.php not found: {$configCore}\n");
    exit(1);
}

$packagePath = $argv[1] ?? (DNEPRITNEWSLETTER_BUILD_ROOT . '_dist/' . DNEPRITNEWSLETTER_SIGNATURE . '.transport.zip');
$packagePath = realpath($packagePath) ?: $packagePath;

if (!is_file($packagePath) || filesize($packagePath) === 0) {
    fwrite(STDERR, "Transport package not found: {$packagePath}\n");
    exit(1);
}

if (basename($packagePath) !== DNEPRITNEWSLETTER_SIGNATURE . '.transport.zip') {
    fwrite(STDERR, "Unexpected package signature: " . basename($packagePath) . "\n");
    exit(1);
}

require_once $configCore;
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');
$modx->getService('error', 'error.modError');

if (!$modx->loadClass('transport.xPDOTransport', XPDO_CORE_PATH, true, true)) {
    throw new RuntimeException('Could not load xPDOTransport.');
}

$packagesPath = MODX_CORE_PATH . 'packages/';
if (!is_dir($packagesPath) && !mkdir($packagesPath, 0775, true) && !is_dir($packagesPath)) {
    throw new RuntimeException('Could not create MODX packages directory.');
}

$unpackedPath = $packagesPath . DNEPRITNEWSLETTER_SIGNATURE;
if (is_dir($unpackedPath)) {
    $cacheManager = $modx->getCacheManager();
    if (!$cacheManager || !$cacheManager->deleteTree($unpackedPath, true, false, [])) {
        throw new RuntimeException('Could not remove a previous unpacked package.');
    }
}

/** @var xPDOTransport|null $transport */
$transport = xPDOTransport::retrieve($modx, $packagePath, $packagesPath);
if (!$transport) {
    throw new RuntimeException('Could not retrieve the transport package.');
}

if ($transport->signature !== DNEPRITNEWSLETTER_SIGNATURE) {
    throw new RuntimeException('Retrieved transport has an unexpected signature: ' . $transport->signature);
}

if (!$transport->install([
    xPDOTransport::PACKAGE_ACTION => xPDOTransport::ACTION_INSTALL,
])) {
    throw new RuntimeException('Transport installation returned false.');
}

$cacheManager = $modx->getCacheManager();
if ($cacheManager) {
    $cacheManager->refresh();
}

$modelPath = MODX_CORE_PATH . 'components/dnepritnewsletter/model/';
if (!$modx->addPackage('dnepritnewsletter', $modelPath, $modx->getOption('table_prefix'))) {
    throw new RuntimeException('Could not add the installed xPDO package.');
}

$failures = [];
$assert = static function ($condition, $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(
    (bool)$modx->getObject('modNamespace', ['name' => 'dnepritnewsletter']),
    'Namespace was not installed.'
);
$assert(
    (bool)$modx->getObject('modMenu', ['text' => 'dnepritnewsletter']),
    'Manager menu was not installed.'
);

foreach (['DnepritNewsletterSubscribe', 'DnepritNewsletterUnsubscribe'] as $snippetName) {
    /** @var modSnippet|null $snippet */
    $snippet = $modx->getObject('modSnippet', ['name' => $snippetName]);
    $assert((bool)$snippet, 'Snippet was not installed: ' . $snippetName);
    if ($snippet) {
        $assert(trim((string)$snippet->get('snippet')) !== '', 'Snippet source is empty: ' . $snippetName);
    }
}

$settingKeys = [
    'dnepritnewsletter.batch_size',
    'dnepritnewsletter.limit_per_minute',
    'dnepritnewsletter.limit_per_hour',
    'dnepritnewsletter.max_attempts',
    'dnepritnewsletter.retry_delay',
    'dnepritnewsletter.lock_ttl',
    'dnepritnewsletter.failure_limit',
    'dnepritnewsletter.sender_email',
    'dnepritnewsletter.sender_name',
    'dnepritnewsletter.reply_to',
    'dnepritnewsletter.unsubscribe_resource_id',
    'dnepritnewsletter.import_max_size',
    'dnepritnewsletter.require_consent',
    'dnepritnewsletter.reactivate_unsubscribed',
    'dnepritnewsletter.subscribe_min_seconds',
    'dnepritnewsletter.subscribe_token_ttl',
    'dnepritnewsletter.subscribe_ip_limit',
    'dnepritnewsletter.subscribe_ip_window',
    'dnepritnewsletter.subscribe_email_limit',
    'dnepritnewsletter.subscribe_email_window',
];

foreach ($settingKeys as $settingKey) {
    $assert(
        (bool)$modx->getObject('modSystemSetting', ['key' => $settingKey]),
        'System setting was not installed: ' . $settingKey
    );
}

$classNames = [
    'DnepritNewsletterSubscriber',
    'DnepritNewsletterCampaign',
    'DnepritNewsletterQueue',
    'DnepritNewsletterLog',
];

foreach ($classNames as $className) {
    $tableName = trim($modx->getTableName($className), '`');
    $statement = $modx->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([$tableName]);
    $assert((int)$statement->fetchColumn() === 1, 'Database table was not created: ' . $tableName);
}

$managerControllerFile = MODX_CORE_PATH . 'components/dnepritnewsletter/index.class.php';
$requiredInstalledFiles = [
    $managerControllerFile,
    MODX_CORE_PATH . 'components/dnepritnewsletter/controllers/home.class.php',
    MODX_CORE_PATH . 'components/dnepritnewsletter/model/dnepritnewsletter/dnepritnewsletter.class.php',
    MODX_CORE_PATH . 'components/dnepritnewsletter/processors/web/subscribe.class.php',
    MODX_CORE_PATH . 'components/dnepritnewsletter/cron/send.php',
    MODX_ASSETS_PATH . 'components/dnepritnewsletter/connector.php',
    MODX_ASSETS_PATH . 'components/dnepritnewsletter/subscribe.php',
    MODX_ASSETS_PATH . 'components/dnepritnewsletter/js/web/subscribe.js',
    MODX_ASSETS_PATH . 'components/dnepritnewsletter/css/web.css',
];

foreach ($requiredInstalledFiles as $requiredInstalledFile) {
    $assert(is_file($requiredInstalledFile), 'Installed file is missing: ' . $requiredInstalledFile);
}

if (is_file($managerControllerFile)) {
    $modx->loadClass('modManagerController', '', false, true);
    require_once $managerControllerFile;

    $indexClass = 'DnepritnewsletterIndexManagerController';
    $homeClass = 'DnepritNewsletterHomeManagerController';

    $assert(class_exists($indexClass, false), 'Expected manager index controller class was not declared.');
    $assert(class_exists($homeClass, false), 'Manager home controller class was not loaded.');
    $assert(
        class_exists($indexClass, false) && class_exists($homeClass, false) && is_subclass_of($indexClass, $homeClass),
        'Manager index controller does not render the home controller implementation.'
    );
}

$serviceClass = MODX_CORE_PATH . 'components/dnepritnewsletter/model/dnepritnewsletter/dnepritnewsletter.class.php';
if (is_file($serviceClass)) {
    require_once $serviceClass;
    $service = new DnepritNewsletter($modx);
    $assert(
        $service->config['publicConnectorUrl'] === MODX_ASSETS_URL . 'components/dnepritnewsletter/subscribe.php',
        'Public connector URL is not configured correctly.'
    );
}

if ($failures) {
    fwrite(STDERR, "DnepritNewsletter install smoke test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

$result = [
    'success' => true,
    'signature' => $transport->signature,
    'package' => $packagePath,
    'tables' => count($classNames),
    'settings' => count($settingKeys),
    'snippets' => 2,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
