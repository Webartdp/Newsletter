<?php

$corePath = $modx->getOption(
    'dnepritnewsletter.core_path',
    null,
    $modx->getOption('core_path') . 'components/dnepritnewsletter/'
);
require_once $corePath . 'model/dnepritnewsletter/dnepritnewsletter.class.php';
require_once $corePath . 'model/dnepritnewsletter/dnepritnewsletterpublicguard.class.php';

$service = new DnepritNewsletter($modx);
$guard = new DnepritNewsletterPublicGuard($modx);
$modx->lexicon->load('dnepritnewsletter:web');

$source = strtolower(trim((string)$modx->getOption('source', $scriptProperties, 'website')));
$source = preg_replace('/[^a-z0-9_.-]+/', '-', $source);
$source = trim($source, '-');
$source = $source !== '' ? substr($source, 0, 100) : 'website';
$showName = filter_var($modx->getOption('showName', $scriptProperties, true), FILTER_VALIDATE_BOOLEAN);
$requireName = filter_var($modx->getOption('requireName', $scriptProperties, false), FILTER_VALIDATE_BOOLEAN);
$requireConsent = filter_var(
    $modx->getOption(
        'requireConsent',
        $scriptProperties,
        $modx->getOption('dnepritnewsletter.require_consent', null, true)
    ),
    FILTER_VALIDATE_BOOLEAN
);
$loadCss = filter_var($modx->getOption('loadCss', $scriptProperties, true), FILTER_VALIDATE_BOOLEAN);
$loadJs = filter_var($modx->getOption('loadJs', $scriptProperties, true), FILTER_VALIDATE_BOOLEAN);
$tpl = trim((string)$modx->getOption('tpl', $scriptProperties, ''));

$formToken = $guard->issue([
    'action' => 'subscribe',
    'source' => $source,
    'require_name' => $requireName,
    'require_consent' => $requireConsent,
]);

if ($formToken === '') {
    return '<div class="dneprit-newsletter-message dneprit-newsletter-message-error">'
        . htmlspecialchars($modx->lexicon('dnepritnewsletter_web_err_session'), ENT_QUOTES, 'UTF-8')
        . '</div>';
}

if ($loadCss) {
    $modx->regClientCSS($service->config['assetsUrl'] . 'css/web.css');
}
if ($loadJs) {
    $modx->regClientScript($service->config['assetsUrl'] . 'js/web/subscribe.js');
}

static $instance = 0;
$instance++;
$resourceId = $modx->resource ? (int)$modx->resource->get('id') : 0;
$formId = trim((string)$modx->getOption('formId', $scriptProperties, ''));
if ($formId === '') {
    $formId = 'dneprit-newsletter-form-' . $resourceId . '-' . $instance;
}
$formId = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $formId);

$placeholders = [
    'form_id' => $formId,
    'connector_url' => $service->config['publicConnectorUrl'],
    'form_token' => $formToken,
    'source' => $source,
    'show_name' => $showName ? 1 : 0,
    'require_name' => $requireName ? 1 : 0,
    'require_consent' => $requireConsent ? 1 : 0,
    'email_label' => (string)$modx->getOption('emailLabel', $scriptProperties, $modx->lexicon('dnepritnewsletter_web_email_label')),
    'email_placeholder' => (string)$modx->getOption('emailPlaceholder', $scriptProperties, $modx->lexicon('dnepritnewsletter_web_email_placeholder')),
    'name_label' => (string)$modx->getOption('nameLabel', $scriptProperties, $modx->lexicon('dnepritnewsletter_web_name_label')),
    'name_placeholder' => (string)$modx->getOption('namePlaceholder', $scriptProperties, $modx->lexicon('dnepritnewsletter_web_name_placeholder')),
    'consent_text' => (string)$modx->getOption('consentText', $scriptProperties, $modx->lexicon('dnepritnewsletter_web_consent')),
    'button_text' => (string)$modx->getOption('buttonText', $scriptProperties, $modx->lexicon('dnepritnewsletter_web_button')),
    'form_class' => trim((string)$modx->getOption('formClass', $scriptProperties, 'dneprit-newsletter-form')),
];

if ($tpl !== '') {
    return $modx->getChunk($tpl, $placeholders);
}

$escape = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$nameField = '';
if ($showName) {
    $nameField = '<div class="dneprit-newsletter-field">'
        . '<label for="' . $escape($formId) . '-name">' . $escape($placeholders['name_label']) . '</label>'
        . '<input type="text" id="' . $escape($formId) . '-name" name="name" maxlength="255" '
        . 'autocomplete="name" placeholder="' . $escape($placeholders['name_placeholder']) . '"'
        . ($requireName ? ' required' : '') . '>'
        . '</div>';
}

return '<form id="' . $escape($formId) . '" class="' . $escape($placeholders['form_class']) . '" '
    . 'action="' . $escape($placeholders['connector_url']) . '" method="post" data-dneprit-newsletter-form>'
    . '<input type="hidden" name="form_token" value="' . $escape($formToken) . '">'
    . '<div class="dneprit-newsletter-honeypot" aria-hidden="true">'
    . '<label>Website<input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>'
    . '</div>'
    . $nameField
    . '<div class="dneprit-newsletter-field">'
    . '<label for="' . $escape($formId) . '-email">' . $escape($placeholders['email_label']) . '</label>'
    . '<input type="email" id="' . $escape($formId) . '-email" name="email" maxlength="255" '
    . 'autocomplete="email" inputmode="email" placeholder="' . $escape($placeholders['email_placeholder']) . '" required>'
    . '</div>'
    . '<label class="dneprit-newsletter-consent">'
    . '<input type="checkbox" name="consent" value="1"' . ($requireConsent ? ' required' : '') . '> '
    . '<span>' . $escape($placeholders['consent_text']) . '</span>'
    . '</label>'
    . '<button type="submit" class="dneprit-newsletter-submit">' . $escape($placeholders['button_text']) . '</button>'
    . '<div class="dneprit-newsletter-message" data-dneprit-newsletter-message role="status" aria-live="polite"></div>'
    . '</form>';
