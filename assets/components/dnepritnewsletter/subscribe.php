<?php

define('MODX_API_MODE', true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed',
        'object' => [],
    ]);
    exit;
}

require_once dirname(__DIR__, 3) . '/config.core.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('web');
$modx->getService('error', 'error.modError');

$corePath = $modx->getOption(
    'dnepritnewsletter.core_path',
    null,
    $modx->getOption('core_path') . 'components/dnepritnewsletter/'
);
require_once $corePath . 'model/dnepritnewsletter/dnepritnewsletter.class.php';
$service = new DnepritNewsletter($modx);

$properties = [
    'email' => $_POST['email'] ?? '',
    'name' => $_POST['name'] ?? '',
    'consent' => $_POST['consent'] ?? '',
    'website' => $_POST['website'] ?? '',
    'form_token' => $_POST['form_token'] ?? '',
];

$response = $modx->runProcessor('web/subscribe', $properties, [
    'processors_path' => $service->config['processorsPath'],
]);

if (!$response) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $modx->lexicon('dnepritnewsletter_web_err_save'),
        'object' => [],
    ]);
    exit;
}

$output = $response->getResponse();
echo is_string($output) ? $output : json_encode($output);
