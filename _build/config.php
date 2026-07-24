<?php

declare(strict_types=1);

$basePath = getenv('MODX_BASE_PATH');

if (!$basePath) {
    $basePath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
}

define('DNEPRITNEWSLETTER_BUILD_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('DNEPRITNEWSLETTER_MODX_BASE_PATH', rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR);
define('DNEPRITNEWSLETTER_VERSION', '0.1.0');
define('DNEPRITNEWSLETTER_RELEASE', 'beta1');
define('DNEPRITNEWSLETTER_SIGNATURE', 'dnepritnewsletter-' . DNEPRITNEWSLETTER_VERSION . '-' . DNEPRITNEWSLETTER_RELEASE);
