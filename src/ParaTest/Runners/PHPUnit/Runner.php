<?php namespace ParaTest\Runners\PHPUnit;

class Runner
{
    protected $maxProcs;
    protected $suite;
    protected $pending = array();
    protected $running = array();
    protected $options;
    protected $printer;
    
    public function __construct($opts = array())
    {
        $opts = array_merge(self::defaults(), $opts);
        $this->maxProcs = $opts['maxProcs'];
        $this->suite = $opts['suite'];
        $this->options = $opts;
        $this->printer = new ResultPrinter();
    }

    public function run()
    {
        $this->printer->startTimer();
        $this->load();        
        while(count($this->running) || count($this->pending)) {
            $this->fillRunQueue();
            $this->running = array_filter($this->running, array($this, 'suiteIsStillRunning'));
        }
        $this->printer->printOutput();
    }

    private function load()
    {
        $loader = new SuiteLoader();
        $loader->loadDir($this->suite);
        $this->pending = array_merge($this->pending, $loader->getSuites());
        foreach($this->pending as $pending)
            $this->printer->addSuite($pending);
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