<?php

declare(strict_types=1);

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

//TEST CONSTANTS
define('FIXTURES', __DIR__ . DS . 'fixtures');
define('PARATEST_ROOT', dirname(__DIR__));
define('PARA_BINARY', PARATEST_ROOT . DS . 'bin' . DS . 'paratest');
define('PARA_BINARY_WINDOWS', PARATEST_ROOT . DS . 'bin' . DS . 'paratest.bat');
define('PHPUNIT', PARATEST_ROOT . DS . 'vendor' . DS . 'phpunit' . DS . 'phpunit' . DS . 'phpunit');
define('BOOTSTRAP', __DIR__ . DS . 'bootstrap.php');
define('PHPUNIT_CONFIGURATION', PARATEST_ROOT . DS . 'phpunit.xml.dist');
