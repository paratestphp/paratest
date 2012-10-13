<?php namespace ParaTest\Runners;

use ParaTest\Parser\Parser;

class PHPUnitRunner
{
    protected $maxProcs;
    protected $loadedSuites;
    protected $parallelSuites;

    private static $testPattern = '/.+Test.php$/';
    private static $dotPattern = '/([.]+)$/';
    private static $testMethod = '/^test/';

    public function __construct($maxProcs = 5)
    {
        $this->maxProcs = $maxProcs;
        $this->loadedSuites = array();
        $this->parallelSuites = array();
    }

    public function loadDir($path)
    {
        if(!is_dir($path)) throw new \InvalidArgumentException("$path is not a valid directory");
        $files = scandir($path);
        foreach($files as $file)
            $this->tryLoadTests($path . DIRECTORY_SEPARATOR . $file);
        $this->initParallelSuites();
    }

    public function tryLoadTests($path)
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
            if($class = $parser->getClassAnnotatedWith('runParallel')) {
                $functions = array_filter($class->getFunctions(), function($fn) {
                    return preg_match(self::$testMethod, $fn->getName()) || $fn->hasAnnotation('test');
                });
                $this->parallelSuites[$suite] = array_map(function($f){return $f->getName();}, $functions);
            }
        }
    }
}