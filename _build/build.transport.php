<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$configCore = DNEPRITNEWSLETTER_MODX_BASE_PATH . 'config.core.php';
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
$modx->getService('error', 'error.modError');

if (!$modx->loadClass('transport.modPackageBuilder', '', false, true)) {
    throw new RuntimeException('Could not load MODX package builder.');
}

$root = DNEPRITNEWSLETTER_BUILD_ROOT;
$core = $root . 'core/components/dnepritnewsletter/';
$assets = $root . 'assets/components/dnepritnewsletter/';
$model = $core . 'model/';
$schema = $core . 'schema/dnepritnewsletter.mysql.schema.xml';
$changelog = $root . 'CHANGELOG.md';
$license = $root . 'LICENSE';
$readme = $root . 'README.md';
$tablesResolver = __DIR__ . '/resolvers/resolve.tables.php';

$requiredFiles = [
    $schema,
    $changelog,
    $license,
    $readme,
    $tablesResolver,
    $core . 'elements/snippets/subscribe.snippet.php',
    $core . 'elements/snippets/unsubscribe.snippet.php',
    $assets . 'subscribe.php',
];

foreach ($requiredFiles as $requiredFile) {
    if (!is_file($requiredFile)) {
        throw new RuntimeException('Required build source is missing: ' . $requiredFile);
    }
}

$manager = $modx->getManager();
$generator = $manager->getGenerator();
if (!$generator->parseSchema($schema, $model)) {
    throw new RuntimeException('Unable to generate the xPDO model from schema.');
}

/** @var modPackageBuilder $builder */
$builder = new modPackageBuilder($modx);
$package = $builder->createPackage(
    'dnepritnewsletter',
    DNEPRITNEWSLETTER_VERSION,
    DNEPRITNEWSLETTER_RELEASE
);

if (!$package || $builder->getSignature() !== DNEPRITNEWSLETTER_SIGNATURE) {
    throw new RuntimeException('Could not create the expected transport signature.');
}

if (!$builder->registerNamespace(
    'dnepritnewsletter',
    false,
    false,
    '{core_path}components/dnepritnewsletter/',
    '{assets_path}components/dnepritnewsletter/'
)) {
    throw new RuntimeException('Could not register the component namespace.');
}

$namespaceVehicle = $builder->createVehicle($builder->namespace, [
    xPDOTransport::UNIQUE_KEY => 'name',
    xPDOTransport::PRESERVE_KEYS => true,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::ABORT_INSTALL_ON_VEHICLE_FAIL => true,
]);

if (!$namespaceVehicle->resolve('file', [
    'source' => $core,
    'target' => "return MODX_CORE_PATH . 'components/';",
])) {
    throw new RuntimeException('Could not add the core file resolver.');
}

if (!$namespaceVehicle->resolve('file', [
    'source' => $assets,
    'target' => "return MODX_ASSETS_PATH . 'components/';",
])) {
    throw new RuntimeException('Could not add the assets file resolver.');
}

if (!$namespaceVehicle->resolve('php', [
    'source' => $tablesResolver,
])) {
    throw new RuntimeException('Could not add the database resolver.');
}

if (!$builder->putVehicle($namespaceVehicle)) {
    throw new RuntimeException('Could not package the namespace vehicle.');
}

$menu = $modx->newObject('modMenu');
$menu->fromArray([
    'text' => 'dnepritnewsletter',
    'parent' => 'components',
    'action' => 'index',
    'description' => 'dnepritnewsletter_menu_desc',
    'icon' => '',
    'menuindex' => 0,
    'params' => '',
    'handler' => '',
    'namespace' => 'dnepritnewsletter',
], '', true, true);

if (!$builder->putVehicle($builder->createVehicle($menu, [
    xPDOTransport::UNIQUE_KEY => 'text',
    xPDOTransport::PRESERVE_KEYS => true,
    xPDOTransport::UPDATE_OBJECT => true,
]))) {
    throw new RuntimeException('Could not package the manager menu.');
}

$snippets = [
    'DnepritNewsletterSubscribe' => [
        'description' => 'Public AJAX newsletter subscription form.',
        'source' => $core . 'elements/snippets/subscribe.snippet.php',
    ],
    'DnepritNewsletterUnsubscribe' => [
        'description' => 'Secure public unsubscribe confirmation page.',
        'source' => $core . 'elements/snippets/unsubscribe.snippet.php',
    ],
];

foreach ($snippets as $name => $data) {
    $snippet = $modx->newObject('modSnippet');
    $snippet->fromArray([
        'name' => $name,
        'description' => $data['description'],
        'snippet' => file_get_contents($data['source']),
    ], '', true, true);

    if (!$builder->putVehicle($builder->createVehicle($snippet, [
        xPDOTransport::UNIQUE_KEY => 'name',
        xPDOTransport::PRESERVE_KEYS => true,
        xPDOTransport::UPDATE_OBJECT => true,
    ]))) {
        throw new RuntimeException('Could not package snippet: ' . $name);
    }
}

$settings = [
    'dnepritnewsletter.batch_size' => ['value' => 50, 'xtype' => 'numberfield'],
    'dnepritnewsletter.limit_per_minute' => ['value' => 50, 'xtype' => 'numberfield'],
    'dnepritnewsletter.limit_per_hour' => ['value' => 500, 'xtype' => 'numberfield'],
    'dnepritnewsletter.max_attempts' => ['value' => 3, 'xtype' => 'numberfield'],
    'dnepritnewsletter.retry_delay' => ['value' => 300, 'xtype' => 'numberfield'],
    'dnepritnewsletter.lock_ttl' => ['value' => 3600, 'xtype' => 'numberfield'],
    'dnepritnewsletter.failure_limit' => ['value' => 3, 'xtype' => 'numberfield'],
    'dnepritnewsletter.sender_email' => ['value' => '', 'xtype' => 'textfield'],
    'dnepritnewsletter.sender_name' => ['value' => '', 'xtype' => 'textfield'],
    'dnepritnewsletter.reply_to' => ['value' => '', 'xtype' => 'textfield'],
    'dnepritnewsletter.unsubscribe_resource_id' => ['value' => 0, 'xtype' => 'numberfield'],
    'dnepritnewsletter.import_max_size' => ['value' => 10485760, 'xtype' => 'numberfield'],
    'dnepritnewsletter.require_consent' => ['value' => 1, 'xtype' => 'combo-boolean'],
    'dnepritnewsletter.reactivate_unsubscribed' => ['value' => 1, 'xtype' => 'combo-boolean'],
    'dnepritnewsletter.subscribe_min_seconds' => ['value' => 2, 'xtype' => 'numberfield'],
    'dnepritnewsletter.subscribe_token_ttl' => ['value' => 7200, 'xtype' => 'numberfield'],
    'dnepritnewsletter.subscribe_ip_limit' => ['value' => 10, 'xtype' => 'numberfield'],
    'dnepritnewsletter.subscribe_ip_window' => ['value' => 600, 'xtype' => 'numberfield'],
    'dnepritnewsletter.subscribe_email_limit' => ['value' => 3, 'xtype' => 'numberfield'],
    'dnepritnewsletter.subscribe_email_window' => ['value' => 3600, 'xtype' => 'numberfield'],
];

foreach ($settings as $key => $data) {
    $setting = $modx->newObject('modSystemSetting');
    $setting->fromArray([
        'key' => $key,
        'value' => $data['value'],
        'xtype' => $data['xtype'],
        'namespace' => 'dnepritnewsletter',
        'area' => 'dnepritnewsletter_main',
    ], '', true, true);

    if (!$builder->putVehicle($builder->createVehicle($setting, [
        xPDOTransport::UNIQUE_KEY => 'key',
        xPDOTransport::PRESERVE_KEYS => true,
        xPDOTransport::UPDATE_OBJECT => true,
    ]))) {
        throw new RuntimeException('Could not package system setting: ' . $key);
    }
}

$builder->setPackageAttributes([
    'license' => file_get_contents($license),
    'readme' => file_get_contents($readme),
    'changelog' => file_get_contents($changelog),
    'requires' => [
        'php' => '>=7.4',
    ],
]);

if (!$builder->pack()) {
    throw new RuntimeException('Could not pack the transport archive.');
}

$sourcePackage = $builder->directory . $builder->filename;
if (!is_file($sourcePackage) || filesize($sourcePackage) === 0) {
    throw new RuntimeException('Transport archive was not created: ' . $sourcePackage);
}

$distDirectory = $root . '_dist/';
if (!is_dir($distDirectory) && !mkdir($distDirectory, 0775, true) && !is_dir($distDirectory)) {
    throw new RuntimeException('Could not create distribution directory: ' . $distDirectory);
}

$distPackage = $distDirectory . $builder->filename;
if (!copy($sourcePackage, $distPackage)) {
    throw new RuntimeException('Could not copy transport archive to distribution directory.');
}

$checksum = hash_file('sha256', $distPackage);
if ($checksum === false) {
    throw new RuntimeException('Could not calculate package checksum.');
}

file_put_contents($distPackage . '.sha256', $checksum . '  ' . basename($distPackage) . PHP_EOL);
file_put_contents($distDirectory . 'release.json', json_encode([
    'name' => 'dnepritnewsletter',
    'version' => DNEPRITNEWSLETTER_VERSION,
    'release' => DNEPRITNEWSLETTER_RELEASE,
    'signature' => $builder->getSignature(),
    'filename' => basename($distPackage),
    'sha256' => $checksum,
    'built_at' => gmdate('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

$modx->log(modX::LOG_LEVEL_INFO, 'DnepritNewsletter package built successfully.');
$modx->log(modX::LOG_LEVEL_INFO, 'Package: ' . $distPackage);
$modx->log(modX::LOG_LEVEL_INFO, 'SHA-256: ' . $checksum);
