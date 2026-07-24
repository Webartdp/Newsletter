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

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
}

$loadCss = filter_var($modx->getOption('loadCss', $scriptProperties, true), FILTER_VALIDATE_BOOLEAN);
if ($loadCss) {
    $modx->regClientCSS($service->config['assetsUrl'] . 'css/web.css');
}

$tokenParam = trim((string)$modx->getOption('tokenParam', $scriptProperties, 'newsletter_token'));
$tokenParam = preg_replace('/[^a-zA-Z0-9_-]+/', '', $tokenParam);
$tokenParam = $tokenParam !== '' ? $tokenParam : 'newsletter_token';
$newsletterToken = trim((string)($_POST[$tokenParam] ?? $_GET[$tokenParam] ?? ''));
$tplConfirm = trim((string)$modx->getOption('tplConfirm', $scriptProperties, ''));
$tplSuccess = trim((string)$modx->getOption('tplSuccess', $scriptProperties, ''));
$tplError = trim((string)$modx->getOption('tplError', $scriptProperties, ''));
$buttonText = (string)$modx->getOption(
    'buttonText',
    $scriptProperties,
    $modx->lexicon('dnepritnewsletter_web_unsubscribe_button')
);

$render = static function ($tpl, array $placeholders, $defaultHtml) use ($modx) {
    return $tpl !== '' ? $modx->getChunk($tpl, $placeholders) : $defaultHtml;
};
$escape = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

if (!preg_match('/^[a-f0-9]{64}$/i', $newsletterToken)) {
    $message = $modx->lexicon('dnepritnewsletter_web_unsubscribe_invalid');
    return $render(
        $tplError,
        ['message' => $message],
        '<div class="dneprit-newsletter-unsubscribe dneprit-newsletter-unsubscribe-error">' . $escape($message) . '</div>'
    );
}

/** @var DnepritNewsletterSubscriber|null $subscriber */
$subscriber = $modx->getObject('DnepritNewsletterSubscriber', ['unsubscribe_token' => $newsletterToken]);
if (!$subscriber) {
    $message = $modx->lexicon('dnepritnewsletter_web_unsubscribe_invalid');
    return $render(
        $tplError,
        ['message' => $message],
        '<div class="dneprit-newsletter-unsubscribe dneprit-newsletter-unsubscribe-error">' . $escape($message) . '</div>'
    );
}

if ((string)$subscriber->get('status') !== 'active') {
    $message = $modx->lexicon('dnepritnewsletter_web_unsubscribe_already');
    return $render(
        $tplSuccess,
        ['message' => $message, 'email' => (string)$subscriber->get('email')],
        '<div class="dneprit-newsletter-unsubscribe dneprit-newsletter-unsubscribe-success">' . $escape($message) . '</div>'
    );
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_POST['dnepritnewsletter_unsubscribe_confirm']);

if ($isPost) {
    $formToken = trim((string)($_POST['form_token'] ?? ''));
    $inspection = $guard->inspect($formToken, 'unsubscribe', 0, 7200);
    $metadata = $inspection['metadata'];
    $validSubscriber = $inspection['valid']
        && (int)($metadata['subscriber_id'] ?? 0) === (int)$subscriber->get('id')
        && hash_equals((string)($metadata['token_hash'] ?? ''), hash('sha256', $newsletterToken));

    if (!$validSubscriber) {
        $message = $modx->lexicon('dnepritnewsletter_web_unsubscribe_expired');
        return $render(
            $tplError,
            ['message' => $message],
            '<div class="dneprit-newsletter-unsubscribe dneprit-newsletter-unsubscribe-error">' . $escape($message) . '</div>'
        );
    }

    $now = date('Y-m-d H:i:s');
    $subscriber->set('status', 'unsubscribed');
    $subscriber->set('unsubscribed_at', $now);
    $subscriber->set('updated_at', $now);

    if (!$subscriber->save()) {
        $modx->log(
            modX::LOG_LEVEL_ERROR,
            '[DnepritNewsletter] Could not unsubscribe public subscriber ' . (int)$subscriber->get('id')
        );
        $message = $modx->lexicon('dnepritnewsletter_web_unsubscribe_save_error');
        return $render(
            $tplError,
            ['message' => $message],
            '<div class="dneprit-newsletter-unsubscribe dneprit-newsletter-unsubscribe-error">' . $escape($message) . '</div>'
        );
    }

    $guard->consume($formToken);

    /** @var DnepritNewsletterLog $log */
    $log = $modx->newObject('DnepritNewsletterLog');
    $log->fromArray([
        'campaign_id' => null,
        'subscriber_id' => (int)$subscriber->get('id'),
        'queue_id' => null,
        'email' => (string)$subscriber->get('email'),
        'event' => 'public_unsubscribe',
        'level' => 'info',
        'attempt' => 0,
        'message' => $modx->lexicon('dnepritnewsletter_web_log_public_unsubscribe'),
        'created_at' => $now,
    ], '', true, true);
    if (!$log->save()) {
        $modx->log(modX::LOG_LEVEL_WARN, '[DnepritNewsletter] Could not save public unsubscribe log.');
    }

    $message = $modx->lexicon('dnepritnewsletter_web_unsubscribe_success');
    return $render(
        $tplSuccess,
        ['message' => $message, 'email' => (string)$subscriber->get('email')],
        '<div class="dneprit-newsletter-unsubscribe dneprit-newsletter-unsubscribe-success">' . $escape($message) . '</div>'
    );
}

$formToken = $guard->issue([
    'action' => 'unsubscribe',
    'subscriber_id' => (int)$subscriber->get('id'),
    'token_hash' => hash('sha256', $newsletterToken),
]);

if ($formToken === '') {
    $message = $modx->lexicon('dnepritnewsletter_web_err_session');
    return $render(
        $tplError,
        ['message' => $message],
        '<div class="dneprit-newsletter-unsubscribe dneprit-newsletter-unsubscribe-error">' . $escape($message) . '</div>'
    );
}

$message = $modx->lexicon('dnepritnewsletter_web_unsubscribe_confirm');
$placeholders = [
    'message' => $message,
    'email' => (string)$subscriber->get('email'),
    'form_token' => $formToken,
    'newsletter_token' => $newsletterToken,
    'token_param' => $tokenParam,
    'button_text' => $buttonText,
];

$defaultHtml = '<div class="dneprit-newsletter-unsubscribe">'
    . '<p>' . $escape($message) . '</p>'
    . '<form method="post">'
    . '<input type="hidden" name="form_token" value="' . $escape($formToken) . '">'
    . '<input type="hidden" name="' . $escape($tokenParam) . '" value="' . $escape($newsletterToken) . '">'
    . '<button type="submit" name="dnepritnewsletter_unsubscribe_confirm" value="1" '
    . 'class="dneprit-newsletter-submit">' . $escape($buttonText) . '</button>'
    . '</form>'
    . '</div>';

return $render($tplConfirm, $placeholders, $defaultHtml);
