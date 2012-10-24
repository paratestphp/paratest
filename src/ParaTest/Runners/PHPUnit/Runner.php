<?php namespace ParaTest\Runners\PHPUnit;

class Runner
{
    protected $processes;
    protected $path;
    protected $phpunit;
    protected $pending = array();
    protected $running = array();
    protected $options;
    protected $printer;
    
    public function __construct($opts = array())
    {
        foreach(self::defaults() as $opt => $value)
            $opts[$opt] = (isset($opts[$opt])) ? $opts[$opt] : $value;
        $this->processes = $opts['processes'];
        $this->path = $opts['path'];
        $this->phpunit = $opts['phpunit'];
        $this->options = $this->filterOptions($opts);
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
        while(sizeof($this->pending) && sizeof($this->running) < $this->processes)
            $this->running[] = array_shift($this->pending)->run($this->phpunit, $this->options);
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

    private function filterOptions($options)
    {
        return array_diff_key($options, array(
            'processes' => $this->processes,
            'path' => $this->path,
            'phpunit' => $this->phpunit
        ));
    }

    private function getDebug()
    {
        $numRunning = sizeof($this->running);
        $numPending = sizeof($this->pending);
        $numProcs = $this->processes;
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

    private static function defaults()
    {
        return array(
            'processes' => 5,
            'path' => getcwd(),
            'phpunit' => 'phpunit'
        );
    }
}