<?php namespace ParaTest\Runners;

class PHPUnitRunner
{
    protected $maxProcs;
    protected $loadedTests;

    private static $testPattern = '/.+Test.php$/';
    private static $dotPattern = '/([.]+)$/';

    public function __construct($maxProcs = 5)
    {
        $this->maxProcs = $maxProcs;
        $this->loadedTests = array();
    }

    public function loadDir($path)
    {
        if(!is_dir($path)) throw new \InvalidArgumentException("$path is not a valid directory");
        $files = scandir($path);
        foreach($files as $file)
            $this->tryLoadTests($path . DIRECTORY_SEPARATOR . $file);
    }

    public function tryLoadTests($path)
    {
        if(preg_match(self::$testPattern, $path))
                $this->loadedTests[] = $path;

        if(!preg_match(self::$dotPattern, $path) && is_dir($path))
            $this->loadDir($path);
    }
}