<?php namespace ParaTest\Runners\PHPUnit;

class Runner
{
    protected $maxProcs;
    protected $path;
    protected $pending = array();
    protected $running = array();
    protected $options;
    protected $printer;
    
    public function __construct($opts = array())
    {
        foreach(self::defaults() as $opt => $value)
            $opts[$opt] = (isset($opts[$opt])) ? $opts[$opt] : $value;
        $this->maxProcs = $opts['maxProcs'];
        $this->path = $opts['path'];
        $this->options = array_diff_key($opts, array(
            'maxProcs' => $this->maxProcs,
            'path' => $this->path
        ));
        $this->printer = new ResultPrinter();
    }

    public function run()
    {
        $this->load();
        $this->printer->startTimer();
        while(count($this->running) || count($this->pending)) {
            $this->fillRunQueue();
            $this->running = array_filter($this->running, array($this, 'suiteIsStillRunning'));
        }
        $this->printer->printOutput();
    }

    private function load()
    {
        $loader = new SuiteLoader();
        $loader->loadDir($this->path);
        $this->pending = array_merge($this->pending, $loader->getSuites());
        foreach($this->pending as $pending)
            $this->printer->addSuite($pending);
    }

    private function fillRunQueue()
    {
        while(sizeof($this->pending) && sizeof($this->running) < $this->maxProcs)
            $this->running[] = array_shift($this->pending)->run($this->options);
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
            'path' => getcwd()
        );
    }
}