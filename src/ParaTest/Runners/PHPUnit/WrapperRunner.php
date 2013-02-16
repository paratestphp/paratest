<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\Logging\LogInterpreter,
    ParaTest\Logging\JUnit\Writer;

class WrapperRunner
{
    const PHPUNIT_FATAL_ERROR = 255;

    protected $pending = array();
    protected $running = array();
    protected $options;
    protected $interpreter;
    protected $printer;
    protected $exitcode = -1;
    
    public function __construct($opts = array())
    {
        $this->options = new Options($opts);
        $this->interpreter = new LogInterpreter();
        $this->printer = new ResultPrinter($this->interpreter);
    }

    public function run()
    {
        $this->verifyConfiguration();
        $this->load();
        $this->printer->start($this->options);
        $opts = $this->options;
        $phpunit = $opts->phpunit . ' --no-globals-backup';
                    var_dump(count($this->pending));
        for ($i = 0; $i < $opts->processes; $i++) {
            $worker = new Worker();
            $worker->start();
            $this->workers[] = $worker;
        }
        echo "Set up " . count($this->workers) . " workers\n";
        while(count($this->pending)) {
            sleep(1);
            echo "Checking workers\n";
            foreach($this->workers as $key => $worker) {
                if($worker->isFree()) {
                    echo "Worker $key is free, assigning to it.\n";
                    if (isset($worker->tempFile)) {
                        echo "1Reading temp file: $worker->tempFile\n";
                        $this->printer->printFeedbackFromFile($worker->tempFile);
                        unset($worker->tempFile);
                    }
                    $pending = array_shift($this->pending);
                    if (!$pending) {
                        break;
                    }
                    $worker->execute($pending->command($phpunit, $opts->filtered));
                    $worker->tempFile = $pending->getTempFile();
                }
            }
        }

        echo "Terminating workers\n";
        foreach ($this->workers as $worker) {
            $worker->stop();
        }
        echo "Waiting for workers termination\n";
        foreach ($this->workers as $worker) {
            $worker->waitForStop();
                    if (isset($worker->tempFile)) {
                        echo "Reading temp file: $worker->tempFile\n";
                        $this->printer->printFeedbackFromFile($worker->tempFile);
                        unset($worker->tempFile);
                    }
        }
        echo "Waiting for all processes to finish.\n";
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
        foreach($readers as $reader) {
            $reader->removeLog();
        }
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

    /**
    private function fillRunQueue()
    {
        $opts = $this->options;
        $phpunit = $opts->phpunit;
        $phpunit = realpath(__DIR__ . '/../../../../bin/phpunit-wrapper');
        while(sizeof($this->pending) && sizeof($this->running) < $opts->processes)
            $this->running[] = array_shift($this->pending)->run($phpunit, $opts->filtered);
    }

    private function testIsStillRunning($test)
    {
        if(!$test->isDoneRunning()) return true;
        $this->setExitCode($test);
        $test->stop();
        if (static::PHPUNIT_FATAL_ERROR === $test->getExitCode())
            throw new \Exception($test->getStderr(), $test->getExitCode());
        $this->printer->printFeedback($test);
        return false;
    }

    private function setExitCode(ExecutableTest $test)
    {
        $exit = $test->getExitCode();
        if($exit > $this->exitcode)
            $this->exitcode = $exit;
    }
     */
}
