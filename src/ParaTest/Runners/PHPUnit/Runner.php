<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\LogReaders\JUnitXmlLogReader;

class Runner
{
    protected $maxProcs;
    protected $suite;
    protected $pending;
    protected $running;
    protected $time;
    protected $options;
    
    public function __construct($opts = array())
    {
        $opts = array_merge(self::defaults(), $opts);
        $this->maxProcs = $opts['maxProcs'];
        $this->suite = $opts['suite'];
        $this->pending = array();
        $this->running = array();
        $this->time = 0;
        $this->options = $opts;
    }

    public function run()
    {
        $this->time = microtime(true);
        $this->fillRunQueue();
        while(count($this->running))
            $this->running = array_filter($this->running, array($this, 'suiteIsStillRunning'));
        $this->time = microtime(true) - $this->time;
    }

    public function load()
    {
        $loader = new SuiteLoader();
        $loader->loadDir($this->suite);
        $this->pending = array_merge($this->pending, $loader->getParallelSuites());
    }

    private function fillRunQueue()
    {
        while(sizeof($this->pending) && sizeof($this->running) < $this->maxProcs)
            $this->running[] = array_shift($this->pending)->run();
    }

    private function suiteIsStillRunning($suite)
    {
        if($suite->isDoneRunning()) {
            $suite->stop();
            $this->fillRunQueue();
            return false;
        }
        return true;
    }

    private static function defaults()
    {
        return array(
            'maxProcs' => 5,
            'suite' => getcwd()
        );
    }
}