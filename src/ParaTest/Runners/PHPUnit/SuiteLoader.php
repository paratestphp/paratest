<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\Parser\Parser;

class SuiteLoader
{
    protected $files = array();
    protected $loadedSuites = array();

    private static $testPattern = '/.+Test.php$/';
    private static $dotPattern = '/([.]+)$/';
    private static $testMethod = '/^test/';

    public function getSuites()
    {
        return $this->loadedSuites;
    }

    public function getTestMethods()
    {
        $methods = array();
        foreach($this->loadedSuites as $suite)
            $methods = array_merge($methods, $suite->getFunctions());
        return $methods;
    }

    public function loadDir($path)
    {
        if(!is_dir($path)) throw new \InvalidArgumentException("$path is not a valid directory");
        $files = scandir($path);
        foreach($files as $file)
            $this->tryLoadTests($path . DIRECTORY_SEPARATOR . $file);
        $this->initSuites();
    }

    private function tryLoadTests($path)
    {
        if(preg_match(self::$testPattern, $path))
                $this->files[] = $path;

        if(!preg_match(self::$dotPattern, $path) && is_dir($path))
            $this->loadDir($path);
    }

    private function initSuites()
    {
        foreach($this->files as $path) {
            $parser = new Parser($path);
            if($class = $parser->getClass())
                $this->loadedSuites[$path] = new Suite($path, array_map(function($fn) use($path) {
                    return new TestMethod($path, $fn->getName());
                }, $class->getMethods()));
        }
    }
}