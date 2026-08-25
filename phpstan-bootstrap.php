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
