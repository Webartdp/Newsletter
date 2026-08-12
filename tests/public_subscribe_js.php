<?php

declare(strict_types=1);

function expectTrue($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$path = __DIR__ . '/../assets/components/dnepritnewsletter/js/web/subscribe.js';
$javascript = file_get_contents($path);
expectTrue(is_string($javascript) && $javascript !== '', 'Public subscribe JavaScript could not be read.');

$compact = preg_replace('/\s+/', '', $javascript);

expectTrue(
    strpos($compact, 'window.DnepritNewsletterSubscribeLoaded') !== false,
    'Duplicate script registration guard is missing.'
);
expectTrue(
    strpos($compact, 'newFormData()') !== false,
    'Public form data is not built explicitly.'
);
expectTrue(
    strpos($compact, 'newFormData(form)') === false,
    'Public form still serializes the live form after competing submit handlers can mutate it.'
);
expectTrue(
    strpos($compact, "data.append('form_token',token)") !== false,
    'Captured form token is not appended explicitly.'
);
expectTrue(
    strpos($compact, 'event.stopImmediatePropagation()') !== false,
    'Competing submit handlers are not stopped.'
);
expectTrue(
    preg_match("/document\\.addEventListener\\('submit',.*?,true\\);/s", $compact) === 1,
    'Submit handler is not registered in the capture phase.'
);

 echo "Public subscribe JavaScript tests passed.\n";
