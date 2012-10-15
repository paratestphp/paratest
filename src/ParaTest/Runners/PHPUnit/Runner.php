<?php namespace ParaTest\Runners\PHPUnit;

class Runner
{
    protected $maxProcs;
    protected $suite;
    protected $pending;
    protected $processes;
    
    public function __construct($opts = array())
    {
        $opts = array_merge(self::defaults(), $opts);
        $this->maxProcs = $opts['maxProcs'];
        $this->suite = $opts['suite'];
        $this->pending = array();
        $this->processes = array();
    }

    public function load()
    {
        $loader = new SuiteLoader();
        $loader->loadDir($this->suite);
        $this->pending = array_merge($this->pending, $loader->getParallelSuites());
    }

    private static function defaults()
    {
        return array(
            'maxProcs' => 5,
            'suite' => getcwd()
        );
    }
}