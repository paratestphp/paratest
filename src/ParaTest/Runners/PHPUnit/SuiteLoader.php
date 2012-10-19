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
                $this->loadedSuites[$path] = new Suite($path, $this->getTestFunctions($class));
        }
    }

    private function getTestFunctions($class)
    {
        $pattern = self::$testMethod;
        return array_filter($class->getFunctions(), function($fn) use($pattern) {
            return preg_match($pattern, $fn->getName()) || $fn->hasAnnotation('test');
        });
    }
}