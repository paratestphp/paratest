<?php namespace ParaTest\Runners\PHPUnit;

class Runner
{
    protected $maxProcs;
    protected $suite;
    protected $pending = array();
    protected $running = array();
    protected $options;
    
    public function __construct($opts = array())
    {
        $opts = array_merge(self::defaults(), $opts);
        $this->maxProcs = $opts['maxProcs'];
        $this->suite = $opts['suite'];
        $this->options = $opts;
    }

    public function run()
    {
        $this->load();        
        while(count($this->running) || count($this->pending)) {
            $this->fillRunQueue();
            $this->running = array_filter($this->running, array($this, 'suiteIsStillRunning'));
        }
    }

    private function load()
    {
        $loader = new SuiteLoader();
        $loader->loadDir($this->suite);
        $this->pending = array_merge($this->pending, $loader->getSuites());
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