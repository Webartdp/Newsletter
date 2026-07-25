<?php

declare(strict_types=1);

if (!defined('MODX_CORE_PATH')) {
    define('MODX_CORE_PATH', dirname(__DIR__) . '/core/');
}

class modExtraManagerController
{
    public static function getDefaultController()
    {
        return 'index';
    }
}

require_once MODX_CORE_PATH . 'components/dnepritnewsletter/index.class.php';

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

$templateFile = MODX_CORE_PATH . 'components/dnepritnewsletter/elements/templates/mgr/home.tpl';
$sectionFile = dirname(__DIR__) . '/assets/components/dnepritnewsletter/js/mgr/sections/home.js';
$template = is_file($templateFile) ? file_get_contents($templateFile) : '';
$section = is_file($sectionFile) ? file_get_contents($sectionFile) : '';

if (strpos($template, 'id="dnepritnewsletter-panel-home-div"') === false) {
    fwrite(STDERR, "Manager template does not expose the panel render target.\n");
    exit(1);
}

if (strpos($section, "renderTo: 'dnepritnewsletter-panel-home-div'") === false) {
    fwrite(STDERR, "Manager section does not mount the ExtJS panel into the template target.\n");
    exit(1);
}

echo "Manager controller and UI mounting test passed.\n";
