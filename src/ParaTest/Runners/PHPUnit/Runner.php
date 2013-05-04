<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\Logging\LogInterpreter,
    ParaTest\Logging\JUnit\Writer;

class Runner
{
    const PHPUNIT_FATAL_ERROR = 255;

    protected $pending = array();
    protected $running = array();
    protected $options;
    protected $interpreter;
    protected $printer;
    protected $exitcode = -1;
    protected $tokens = array();

    public function __construct($opts = array())
    {
        $this->options = new Options($opts);
        $this->interpreter = new LogInterpreter();
        $this->printer = new ResultPrinter($this->interpreter);
        $this->initTokens();
    }

    public function run()
    {
        $this->verifyConfiguration();
        $this->load();
        $this->printer->start($this->options);
        while(count($this->running) || count($this->pending)) {
            foreach($this->running as $key => $test)
                if(!$this->testIsStillRunning($test)) {
                    unset($this->running[$key]);
                    $this->releaseToken($key);
                }
            $this->fillRunQueue();
            usleep(10000);
        }
        $this->complete();
    }

    public function getExitCode()
    {
        return $this->exitcode;
    }

    private function verifyConfiguration()
    {
        if (isset($this->options->filtered['configuration']) && !file_exists($this->options->filtered['configuration']->getPath())) {
            $this->printer->println(sprintf('Could not read "%s".', $this->options->filtered['configuration']));
            exit(1);
        }
    }

    private function complete()
    {
        $this->printer->printResults();
        $this->interpreter->rewind();
        $this->log();
        $readers = $this->interpreter->getReaders();
        foreach($readers as $reader)
            $reader->removeLog();
    }

    private function load()
    {
        $loader = new SuiteLoader($this->options);
        $loader->load($this->options->path);
        $executables = ($this->options->functional) ? $loader->getTestMethods() : $loader->getSuites();
        $this->pending = array_merge($this->pending, $executables);
        foreach($this->pending as $pending)
            $this->printer->addTest($pending);
    }

    private function log()
    {
        if(!isset($this->options->filtered['log-junit'])) return;
        $output = $this->options->filtered['log-junit'];
        $writer = new Writer($this->interpreter, $this->options->path);
        $writer->write($output);
    }

    private function fillRunQueue()
    {
        $opts = $this->options;
        while(sizeof($this->pending) && sizeof($this->running) < $opts->processes) {
            $token = $this->getNextAvailableToken();
            if ($token !== false) {
                $this->acquireToken($token);
                $this->running[$token] = array_shift($this->pending)->run($opts->phpunit, $opts->filtered, array('TEST_TOKEN' => $token));
            }
        }
    }

    private function testIsStillRunning($test)
    {
        if(!$test->isDoneRunning()) return true;
        $this->setExitCode($test);
        $test->stop();
        if (static::PHPUNIT_FATAL_ERROR === $test->getExitCode())
            throw new \Exception($test->getStderr());
        $this->printer->printFeedback($test);
        return false;
    }

    private function setExitCode(ExecutableTest $test)
    {
        $exit = $test->getExitCode();
        if($exit > $this->exitcode)
            $this->exitcode = $exit;
    }

    protected function initTokens()
    {
        $this->tokens = array();
        for ($i=0; $i< $this->options->processes; $i++) {
            $this->tokens[$i] = true;
        }
    }

    protected function getNextAvailableToken()
    {
        for ($i=0; $i< count($this->tokens); $i++) {
            if ($this->tokens[$i]) return $i;
        }
        return false;
    }

    protected function releaseToken($tokenIdentifier)
    {
        $this->tokens[$tokenIdentifier] = true;
    }

    protected function acquireToken($tokenIdentifier)
    {
        $this->tokens[$tokenIdentifier] = false;
    }
}