<?php
if(!defined('DS'))
    define('DS', DIRECTORY_SEPARATOR);

function bin_autoload($className)
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

    if(file_exists($srcFile)) require $srcFile;
    else require $fileName;
}
spl_autoload_register('bin_autoload');