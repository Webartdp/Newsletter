<?php

/** @var xPDOObject $object */
/** @var array $options */

if (!isset($object) || !($object instanceof xPDOObject) || !$object->xpdo) {
    return false;
}

/** @var modX $modx */
$modx = $object->xpdo;
$action = isset($options[xPDOTransport::PACKAGE_ACTION])
    ? (int)$options[xPDOTransport::PACKAGE_ACTION]
    : xPDOTransport::ACTION_INSTALL;

if ($action === xPDOTransport::ACTION_UNINSTALL) {
    return true;
}

$modelPath = MODX_CORE_PATH . 'components/dnepritnewsletter/model/';
if (!$modx->addPackage('dnepritnewsletter', $modelPath, $modx->config['table_prefix'])) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[DnepritNewsletter] Could not load the installed xPDO package.');
    return false;
}

$manager = $modx->getManager();
$classes = [
    'DnepritNewsletterSubscriber',
    'DnepritNewsletterCampaign',
    'DnepritNewsletterQueue',
    'DnepritNewsletterLog',
];

foreach ($classes as $class) {
    $tableName = trim($modx->getTableName($class), '`');
    $statement = $modx->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );

    if (!$statement || !$statement->execute([$tableName])) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[DnepritNewsletter] Could not inspect table for ' . $class);
        return false;
    }

    $exists = (int)$statement->fetchColumn() > 0;
    if (!$exists && !$manager->createObjectContainer($class)) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[DnepritNewsletter] Could not create table for ' . $class);
        return false;
    }
}

$fieldsByClass = [
    'DnepritNewsletterQueue' => [
        'subject',
        'body_html',
        'body_text',
        'sender_email',
        'sender_name',
        'reply_to',
    ],
    'DnepritNewsletterCampaign' => [
        'skipped_count',
    ],
];

foreach ($fieldsByClass as $class => $fields) {
    $table = $modx->getTableName($class);

    foreach ($fields as $field) {
        $statement = $modx->query('SHOW COLUMNS FROM ' . $table . ' LIKE ' . $modx->quote($field));
        $exists = $statement && $statement->fetch(PDO::FETCH_ASSOC);

        if (!$exists && !$manager->addField($class, $field)) {
            $modx->log(
                modX::LOG_LEVEL_ERROR,
                '[DnepritNewsletter] Could not add field ' . $class . '.' . $field
            );
            return false;
        }
    }
}

return true;
