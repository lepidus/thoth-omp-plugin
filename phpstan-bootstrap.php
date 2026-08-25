<?php

if (!defined('PKP_STRICT_MODE')) {
    define('PKP_STRICT_MODE', false);
}

if (!defined('INDEX_FILE_LOCATION')) {
    $thothPkpRoot = getenv('THOTH_PKP_ROOT');
    define(
        'INDEX_FILE_LOCATION',
        is_string($thothPkpRoot) && $thothPkpRoot !== ''
            ? rtrim($thothPkpRoot, '/') . '/index.php'
            : __FILE__
    );
}

$thothPkpRoot = getenv('THOTH_PKP_ROOT');
if (!is_string($thothPkpRoot) || $thothPkpRoot === '') {
    return;
}

$thothPkpRoot = rtrim($thothPkpRoot, '/');
$previousWorkingDirectory = getcwd();

if (!defined('BASE_SYS_DIR')) {
    define('BASE_SYS_DIR', $thothPkpRoot);
}

chdir($thothPkpRoot);
$loader = require $thothPkpRoot . '/lib/pkp/lib/vendor/autoload.php';
$loader->addPsr4('APP\\', $thothPkpRoot . '/classes');
require_once $thothPkpRoot . '/lib/pkp/includes/functions.php';

if (is_string($previousWorkingDirectory)) {
    chdir($previousWorkingDirectory);
}
