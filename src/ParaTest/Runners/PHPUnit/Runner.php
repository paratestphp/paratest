<?php namespace ParaTest\Runners\PHPUnit;

class Runner
{
    protected $pending = array();
    protected $running = array();
    protected $options;
    protected $printer;
    
    public function __construct($opts = array())
    {
        $this->options = new Options($opts);
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
        $loader->loadDir($this->options->path);
        $this->pending = array_merge($this->pending, $loader->getSuites());
        foreach($this->pending as $pending)
            $this->printer->addSuite($pending);
    }

    private function fillRunQueue()
    {
        $opts = $this->options;
        while(sizeof($this->pending) && sizeof($this->running) < $opts->processes)
            $this->running[] = array_shift($this->pending)->run($opts->phpunit, $opts->filtered);
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

    private function getDebug()
    {
        $numRunning = sizeof($this->running);
        $numPending = sizeof($this->pending);
        $numProcs = $this->options->processes;
        return sprintf(
            "\nRunning: %d\nPending: %d\nProcs: %d\nPath: %s\n\n",
            $numRunning,
            $numPending,
            $numProcs,
            $this->path);
    }

    private function logDebug()
    {
        $file = __DIR__ . DS . 'log.txt';
        file_put_contents($file, $this->getDebug(), FILE_APPEND);
    }

}