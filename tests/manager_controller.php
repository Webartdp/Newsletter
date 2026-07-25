<?php

declare(strict_types=1);

class modExtraManagerController
{
    public static function getDefaultController()
    {
        return 'index';
    }
}

require_once dirname(__DIR__) . '/core/components/dnepritnewsletter/index.class.php';

$method = new ReflectionMethod(DnepritNewsletterManagerController::class, 'getDefaultController');

if (!$method->isStatic()) {
    fwrite(STDERR, "DnepritNewsletterManagerController::getDefaultController() must be static.\n");
    exit(1);
}

if (DnepritNewsletterManagerController::getDefaultController() !== 'home') {
    fwrite(STDERR, "Unexpected default manager controller.\n");
    exit(1);
}

echo "Manager controller compatibility test passed.\n";
