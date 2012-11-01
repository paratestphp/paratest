<?php
if(!defined('DS'))
    define('DS', DIRECTORY_SEPARATOR);

require_once dirname(__DIR__) . DS . 'vendor' . DS . 'autoload.php';

//PSR-0 autoloader modified to account for test and src dirs
function autoload($className)
{
    $className = ltrim($className, '\\');
    $fileName  = '';
    $namespace = '';
    if ($lastNsPos = strripos($className, '\\')) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName  = str_replace('\\', DS, $namespace) . DS;
    }
    $fileName .= str_replace('_', DS, $className) . '.php';

    $srcFile = 'src' . DS . $fileName;
    $testFile = 'test' . DS . $fileName;
    $integrationFile = 'it' . DS . $fileName;

    if(file_exists($srcFile)) require $srcFile;
    if(file_exists($testFile)) require $testFile;
    if(file_exists($integrationFile)) require $integrationFile;
}

spl_autoload_register('autoload');

//TEST CONSTANTS
define('FIXTURES', __DIR__ . DS . 'fixtures');
define("PARA_BINARY", dirname(dirname(__FILE__)) . DS . 'bin' . DS . 'paratest');
define("PARATEST_ROOT", dirname(__DIR__));
//check for .bat first if on windows.
$phpunit_path = PARATEST_ROOT . DS . 'vendor' . DS . 'bin' . DS . 'phpunit';
if(file_exists($phpunit_path . '.bat'))
    $phpunit_path = $phpunit_path . '.bat';
define("PHPUNIT", $phpunit_path);
define('BOOTSTRAP', __FILE__);

require_once __DIR__ . DS . 'TestBase.php';
require_once dirname(__DIR__) . DS . 'functional' . DS . 'FunctionalTestBase.php';