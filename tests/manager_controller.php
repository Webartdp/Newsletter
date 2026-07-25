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

$className = 'DnepritnewsletterIndexManagerController';

if (!class_exists($className, false)) {
    fwrite(STDERR, "Expected MODX root manager controller class was not declared: {$className}.\n");
    exit(1);
}

$method = new ReflectionMethod($className, 'getDefaultController');

if (!$method->isStatic()) {
    fwrite(STDERR, "{$className}::getDefaultController() must be static.\n");
    exit(1);
}

if ($className::getDefaultController() !== 'home') {
    fwrite(STDERR, "Unexpected default manager controller.\n");
    exit(1);
}

echo "Manager controller compatibility test passed.\n";
