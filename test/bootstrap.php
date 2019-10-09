<?php

declare(strict_types=1);

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

require_once dirname(__DIR__) . DS . 'vendor' . DS . 'autoload.php';

//TEST CONSTANTS
define('FIXTURES', __DIR__ . DS . 'fixtures');

$pb = dirname(__DIR__) . DS . 'bin' . DS . 'paratest';
if (defined('PHP_WINDOWS_VERSION_BUILD')) {
    $pb .= '.bat';
}
define('PARA_BINARY', $pb);
define('PARATEST_ROOT', dirname(__DIR__));

$phpunit_path = PARATEST_ROOT . DS . 'vendor' . DS . 'phpunit' . DS . 'phpunit' . DS . 'phpunit';
define('PHPUNIT', $phpunit_path);

define('BOOTSTRAP', __FILE__);
define('PHPUNIT_CONFIGURATION', dirname(__DIR__) . DS . 'phpunit.xml.dist');

require_once __DIR__ . DS . 'TestBase.php';
