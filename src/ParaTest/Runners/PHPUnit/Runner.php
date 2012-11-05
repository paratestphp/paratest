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
        $this->printer->start($this->options);
        while(count($this->running) || count($this->pending)) {
            foreach($this->running as $key => $test)
                if(!$this->testIsStillRunning($test)) unset($this->running[$key]);
            $this->fillRunQueue();
        }
        $this->printer->flush();
    }

    private function load()
    {
        $loader = new SuiteLoader();
        $loader->load($this->options->path);
        $executables = ($this->options->functional) ? $loader->getTestMethods() : $loader->getSuites();
        $this->pending = array_merge($this->pending, $executables);
        foreach($this->pending as $pending)
            $this->printer->addTest($pending);
    }

    private function fillRunQueue()
    {
        $opts = $this->options;
        while(sizeof($this->pending) && sizeof($this->running) < $opts->processes)
            $this->running[] = array_shift($this->pending)->run($opts->phpunit, $opts->filtered);
    }

    private function testIsStillRunning($test)
    {
        if(!$test->isDoneRunning()) return true;
        $test->stop();
        $this->printer->printFeedback($test);
        return false;
    }
}