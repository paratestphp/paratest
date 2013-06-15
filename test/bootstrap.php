<?php
if(!defined('DS'))
    define('DS', DIRECTORY_SEPARATOR);

require_once dirname(__DIR__) . DS . 'vendor' . DS . 'autoload.php';

//TEST CONSTANTS
define('FIXTURES', __DIR__ . DS . 'fixtures');

$pb = dirname(dirname(__FILE__)) . DS . 'bin' . DS . 'paratest';
if (defined('PHP_WINDOWS_VERSION_BUILD')) {
    $pb .= '.bat';
}
define("PARA_BINARY", $pb);
define("PARATEST_ROOT", dirname(__DIR__));

//check for .bat first if on windows.
$phpunit_path = PARATEST_ROOT . DS . 'vendor' . DS . 'bin' . DS . 'phpunit';
if(file_exists($phpunit_path . '.bat')) {
    $phpunit_path = $phpunit_path . '.bat';
}
define("PHPUNIT", $phpunit_path);

define('BOOTSTRAP', __FILE__);
define('PHPUNIT_CONFIGURATION', dirname(__DIR__) . DS . 'phpunit.xml.dist');

require_once __DIR__ . DS . 'TestBase.php';
require_once dirname(__DIR__) . DS . 'functional' . DS . 'FunctionalTestBase.php';
