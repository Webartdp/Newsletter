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
    $tableName = trim($modx->getTableName($class), '`');
    $statement = $modx->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([$tableName]);
    $exists = (int)$statement->fetchColumn() > 0;

    if (!$exists && !$manager->createObjectContainer($class)) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[DnepritNewsletter] Could not create table for ' . $class);
        return false;
    }
}

$queueFields = [
    'subject',
    'body_html',
    'body_text',
    'sender_email',
    'sender_name',
    'reply_to',
];
$queueTable = $modx->getTableName('DnepritNewsletterQueue');

foreach ($queueFields as $field) {
    $statement = $modx->query('SHOW COLUMNS FROM ' . $queueTable . ' LIKE ' . $modx->quote($field));
    $exists = $statement && $statement->fetch(PDO::FETCH_ASSOC);

    if (!$exists && !$manager->addField('DnepritNewsletterQueue', $field)) {
        $modx->log(
            modX::LOG_LEVEL_ERROR,
            '[DnepritNewsletter] Could not add queue field: ' . $field
        );
        return false;
    }
}

return true;
