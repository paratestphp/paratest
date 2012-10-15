<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\Parser\Parser;

class SuiteLoader
{
    protected $loadedSuites;
    protected $parallelSuites;
    protected $serialSuites;

    private static $testPattern = '/.+Test.php$/';
    private static $dotPattern = '/([.]+)$/';
    private static $testMethod = '/^test/';

    public function __construct()
    {
        $this->loadedSuites = array();
        $this->parallelSuites = array();
        $this->serialSuites = array();
    }

    public function loadDir($path)
    {
        if(!is_dir($path)) throw new \InvalidArgumentException("$path is not a valid directory");
        $files = scandir($path);
        foreach($files as $file)
            $this->tryLoadTests($path . DIRECTORY_SEPARATOR . $file);
        $this->initParallelSuites();
        $this->initSerialSuites();
    }

    private function tryLoadTests($path)
    {
        if(preg_match(self::$testPattern, $path))
                $this->loadedSuites[] = $path;

        if(!preg_match(self::$dotPattern, $path) && is_dir($path))
            $this->loadDir($path);
    }

    private function initParallelSuites()
    {
        foreach($this->loadedSuites as $suite) {
            $parser = new Parser($suite);
            if($class = $parser->getClassAnnotatedWith('runParallel'))
                $this->parallelSuites[$suite] = new Suite($suite, $this->getTestFunctions($class));
        }
    }

    private function initSerialSuites()
    {
        foreach($this->loadedSuites as $suite) {
            $parser = new Parser($suite);
            if(!array_key_exists($suite, $this->parallelSuites) && $class = $parser->getClass())
                $this->serialSuites[$suite] = new Suite($suite, $this->getTestFunctions($class));
        }
    }

    private function getTestFunctions($class)
    {
        return array_filter($class->getFunctions(), function($fn) {
            return preg_match(self::$testMethod, $fn->getName()) || $fn->hasAnnotation('test');
        });
    }
}