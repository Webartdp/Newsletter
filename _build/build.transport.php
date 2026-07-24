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

$root = DNEPRITNEWSLETTER_BUILD_ROOT;
$core = $root . 'core/components/dnepritnewsletter/';
$assets = $root . 'assets/components/dnepritnewsletter/';
$model = $core . 'model/';
$schema = $core . 'schema/dnepritnewsletter.mysql.schema.xml';

$manager = $modx->getManager();
$generator = $manager->getGenerator();

if (!$generator->parseSchema($schema, $model)) {
    throw new RuntimeException('Unable to generate xPDO model from schema.');
}

$package = new xPDOTransport($modx);
$package->createPackage('dnepritnewsletter', DNEPRITNEWSLETTER_VERSION, DNEPRITNEWSLETTER_RELEASE);
$package->registerNamespace('dnepritnewsletter', false, true, '{core_path}components/dnepritnewsletter/');

$namespace = $modx->newObject('modNamespace');
$namespace->fromArray([
    'name' => 'dnepritnewsletter',
    'path' => '{core_path}components/dnepritnewsletter/',
    'assets_path' => '{assets_path}components/dnepritnewsletter/',
], '', true, true);

$vehicle = $package->createVehicle($namespace, [
    xPDOTransport::UNIQUE_KEY => 'name',
    xPDOTransport::PRESERVE_KEYS => true,
    xPDOTransport::UPDATE_OBJECT => true,
]);

$vehicle->resolve('file', [
    'source' => $core,
    'target' => "return MODX_CORE_PATH . 'components/';",
]);
$vehicle->resolve('file', [
    'source' => $assets,
    'target' => "return MODX_ASSETS_PATH . 'components/';",
]);
$vehicle->resolve('php', [
    'source' => __DIR__ . '/resolvers/resolve.tables.php',
]);
$package->putVehicle($vehicle);

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
$package->putVehicle($package->createVehicle($menu, [
    xPDOTransport::UNIQUE_KEY => 'text',
    xPDOTransport::PRESERVE_KEYS => true,
    xPDOTransport::UPDATE_OBJECT => true,
]));

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

    $package->putVehicle($package->createVehicle($setting, [
        xPDOTransport::UNIQUE_KEY => 'key',
        xPDOTransport::PRESERVE_KEYS => true,
        xPDOTransport::UPDATE_OBJECT => true,
    ]));
}

$package->setPackageAttributes([
    'license' => file_get_contents($root . 'LICENSE'),
    'readme' => file_get_contents($root . 'README.md'),
    'changelog' => "# Changelog\n\n## 0.1.0-alpha\n\n- Initial component scaffold.\n- Subscriber CRUD management.\n- CSV/TXT subscriber import.\n- Campaign editor and personalized queue preparation.\n- Cron batch sender with limits and retries.\n",
]);

$package->pack();
$modx->log(modX::LOG_LEVEL_INFO, 'DnepritNewsletter package built successfully.');
