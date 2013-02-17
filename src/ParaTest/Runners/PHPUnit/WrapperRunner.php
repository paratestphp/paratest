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
        for ($i = 0; $i < $opts->processes; $i++) {
            $worker = new Worker();
            $worker->start();
            $this->streams[] = $worker->stdout();
            $this->workers[] = $worker;
        }
        $modified = $this->streams;
        $write = array();
        $except = array();
        while(count($this->pending) 
            && stream_select($modified, $write, $except, 1)) {
            foreach($modified as $modifiedStream) {
                $found = null;
                foreach ($this->streams as $index => $stream) {
                    if ($modifiedStream == $stream) {
                        $found = $index;
                        break;
                    }
                }
                $worker = $this->workers[$found];
                if($worker->isFree()) {
                    if (isset($worker->tempFile)) {
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
            $modified = $this->streams;
            $write = array();
            $except = array();
        }

        foreach ($this->workers as $worker) {
            $worker->stop();
        }
        $toStop = $this->workers;
        while (count($toStop) > 0) {
            $modified = array();
            foreach ($toStop as $index => $worker) {
                $modified[$index] = $this->streams[$index];
            }
            $write = array();
            $except = array();
            $new = stream_select($modified, $write, $except, 1);
            if ($new === 0) {
                continue;
            }
            if ($new === false) {
                throw new \RuntimeException("stream_select() returned an error.");
            }
            foreach($modified as $modifiedStream) {
                $found = null;
                foreach ($this->streams as $index => $stream) {
                    if ($modifiedStream == $stream) {
                        $found = $index;
                        break;
                    }
                }
                $worker = $this->workers[$found];
                if (!$worker->isRunning()) {
                    unset($toStop[$found]);
                }
            }
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
