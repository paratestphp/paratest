<?php
if(!defined('DS'))
    define('DS', DIRECTORY_SEPARATOR);

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

define('FIXTURES', __DIR__ . DS . 'fixtures');
define("PARA_BINARY", dirname(dirname(__FILE__)) . DS . 'bin' . DS . 'paratest');

require_once __DIR__ . DS . 'TestBase.php';