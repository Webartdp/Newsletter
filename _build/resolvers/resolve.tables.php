<?php

/** @var xPDOObject $object */
/** @var array $options */

if ($options[xPDOTransport::PACKAGE_ACTION] === xPDOTransport::ACTION_UNINSTALL) {
    return true;
}

$modelPath = MODX_CORE_PATH . 'components/dnepritnewsletter/model/';
$modx->addPackage('dnepritnewsletter', $modelPath, $modx->config['table_prefix']);

$manager = $modx->getManager();
$classes = [
    'DnepritNewsletterSubscriber',
    'DnepritNewsletterCampaign',
    'DnepritNewsletterQueue',
    'DnepritNewsletterLog',
];

foreach ($classes as $class) {
    if (!$manager->createObjectContainer($class)) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[DnepritNewsletter] Could not create table for ' . $class);
        return false;
    }
}

return true;
