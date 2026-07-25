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
$homeClass = 'DnepritNewsletterHomeManagerController';

if (!class_exists($className, false)) {
    fwrite(STDERR, "Expected MODX root manager controller class was not declared: {$className}.\n");
    exit(1);
}

if (!class_exists($homeClass, false)) {
    fwrite(STDERR, "Home manager controller was not loaded: {$homeClass}.\n");
    exit(1);
}

if (!is_subclass_of($className, $homeClass)) {
    fwrite(STDERR, "Root manager controller must extend the full home controller.\n");
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

$templateMethod = new ReflectionMethod($className, 'getTemplateFile');
if ($templateMethod->getDeclaringClass()->getName() !== $homeClass) {
    fwrite(STDERR, "Root manager controller does not inherit the home template implementation.\n");
    exit(1);
}

$assetsMethod = new ReflectionMethod($className, 'loadCustomCssJs');
if ($assetsMethod->getDeclaringClass()->getName() !== $homeClass) {
    fwrite(STDERR, "Root manager controller does not inherit the home asset loader.\n");
    exit(1);
}

echo "Manager controller routing test passed.\n";
