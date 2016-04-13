<?php
namespace ParaTest\Runners\PHPUnit;

class WrapperRunner extends BaseRunner
{
    const PHPUNIT_FAILURES = 1;
    const PHPUNIT_ERRORS = 2;

    /**
     * @var array
     */
    protected $streams;

    /**
     * @var Worker[]
     */
    protected $workers;

    /**
     * @var array
     */
    protected $modified;


    public function run()
    {
        parent::run();

        $this->startWorkers();
        $this->assignAllPendingTests();
        $this->sendStopMessages();
        $this->waitForAllToFinish();
        $this->complete();
    }

    protected function load()
    {
        if ($this->options->functional) {
            throw new \RuntimeException("The `functional` option is not supported yet in the WrapperRunner. Only full classes can be run due to the current PHPUnit commands causing classloading issues.");
        }
        parent::load();
    }

    private function startWorkers()
    {
        $wrapper = realpath(__DIR__ . '/../../../../bin/phpunit-wrapper');
        for ($i = 1; $i <= $this->options->processes; $i++) {
            $worker = new Worker();
            if ($this->options->noTestTokens) {
                $token = null;
                $uniqueToken = null;
            } else {
                $token = $i;
                $uniqueToken = uniqid();
            }
            $worker->start($wrapper, $token, $uniqueToken);
            $this->streams[] = $worker->stdout();
            $this->workers[] = $worker;
        }
    }

    private function assignAllPendingTests()
    {
        $phpunit = $this->options->phpunit;
        $phpunitOptions = $this->options->filtered;
        $phpunitOptions['no-globals-backup'] = null;
        while (count($this->pending)) {
            $this->waitForStreamsToChange($this->streams);
            foreach ($this->progressedWorkers() as $worker) {
                if ($worker->isFree()) {
                    $this->flushWorker($worker);
                    $pending = array_shift($this->pending);
                    if ($pending) {
                        $worker->assign($pending, $phpunit, $phpunitOptions);
                    }
                }
            }
        }
    }

    private function sendStopMessages()
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }
    }

    private function waitForAllToFinish()
    {
        $toStop = $this->workers;
        while (count($toStop) > 0) {
            $toCheck = $this->streamsOf($toStop);
            $new = $this->waitForStreamsToChange($toCheck);
            foreach ($this->progressedWorkers() as $index => $worker) {
                if (!$worker->isRunning()) {
                    $this->flushWorker($worker);
                    unset($toStop[$index]);
                }
            }
        }
    }

    // put on WorkersPool
    private function waitForStreamsToChange($modified)
    {
        $write = array();
        $except = array();
        $result = stream_select($modified, $write, $except, 1);
        if ($result === false) {
            throw new \RuntimeException("stream_select() returned an error while waiting for all workers to finish.");
        }
        $this->modified = $modified;
        return $result;
    }

    /**
     * put on WorkersPool
     * @return Worker[]
     */
    private function progressedWorkers()
    {
        $result = array();
        foreach ($this->modified as $modifiedStream) {
            $found = null;
            foreach ($this->streams as $index => $stream) {
                if ($modifiedStream == $stream) {
                    $found = $index;
                    break;
                }
            }
            $result[$found] = $this->workers[$found];
        }
        $this->modified = array();
        return $result;
    }

    /**
     * Returns the output streams of a subset of workers.
     * @param array    keys are positions in $this->workers
     * @return array
     */
    private function streamsOf($workers)
    {
        $streams = array();
        foreach (array_keys($workers) as $index) {
            $streams[$index] = $this->streams[$index];
        }
        return $streams;
    }

    private function complete()
    {
        $this->setExitCode();
        $this->printer->printResults();
        $this->interpreter->rewind();
        $this->log();
        $this->logCoverage();
        $readers = $this->interpreter->getReaders();
        foreach ($readers as $reader) {
            $reader->removeLog();
        }
    }


    private function setExitCode()
    {
        if ($this->interpreter->getTotalErrors()) {
            $this->exitcode = self::PHPUNIT_ERRORS;
        } elseif ($this->interpreter->getTotalFailures()) {
            $this->exitcode = self::PHPUNIT_FAILURES;
        } else {
            $this->exitcode = 0;
        }
    }

    private function flushWorker($worker)
    {
        if ($this->hasCoverage()) {
            $this->addCoverageFromFile($worker->getCoverageFileName());
        }
        $worker->printFeedback($this->printer);
        $worker->reset();
    }

  /**
    private function testIsStillRunning($test)
    {
        if(!$test->isDoneRunning()) return true;
        $this->setExitCode($test);
        $test->stop();
        if (static::PHPUNIT_FATAL_ERROR === $test->getExitCode())
            throw new \Exception($test->getStderr(), $test->getExitCode());
        return false;
    }
     */
}
