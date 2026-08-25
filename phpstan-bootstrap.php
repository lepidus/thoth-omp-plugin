<?php

declare(strict_types=1);

$thothPkpRoot = getenv('THOTH_PKP_ROOT');

if (!is_string($thothPkpRoot) || $thothPkpRoot === '') {
    return;
}

$thothPkpRoot = rtrim($thothPkpRoot, '/');
$previousWorkingDirectory = getcwd();

define('BASE_SYS_DIR', $thothPkpRoot);
define('INDEX_FILE_LOCATION', $thothPkpRoot . '/index.php');

chdir($thothPkpRoot);
require_once $thothPkpRoot . '/lib/pkp/lib/vendor/autoload.php';
require_once $thothPkpRoot . '/lib/pkp/includes/functions.inc.php';

import('lib.pkp.classes.core.Core');
import('lib.pkp.classes.core.Registry');
import('lib.pkp.classes.config.Config');
import('lib.pkp.classes.db.DAORegistry');
import('lib.pkp.classes.db.XMLDAO');
import('lib.pkp.classes.submission.PKPSubmission');
import('classes.codelist.SubjectDAO');
import('classes.core.Services');
import('classes.file.PublicFileManager');
import('classes.i18n.AppLocale');
import('classes.submission.Submission');

if (is_string($previousWorkingDirectory)) {
    chdir($previousWorkingDirectory);
}
