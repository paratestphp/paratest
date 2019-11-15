<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Worker\WrapperWorker;

class WrapperRunner extends BaseRunner
{
    private const PHPUNIT_FAILURES = 1;

    private const PHPUNIT_ERRORS = 2;

    /**
     * @var array
     */
    protected $streams;

    /**
     * @var WrapperWorker[]
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

    protected function load(SuiteLoader $loader)
    {
        if ($this->options->functional) {
            throw new \RuntimeException(
                'The `functional` option is not supported yet in the WrapperRunner. Only full classes can be run due ' .
                    'to the current PHPUnit commands causing classloading issues.'
            );
        }
        parent::load($loader);
    }

    protected function startWorkers()
    {
        $wrapper = \realpath(__DIR__ . '/../../../bin/phpunit-wrapper');
        for ($i = 1; $i <= $this->options->processes; ++$i) {
            $worker = new WrapperWorker();
            if ($this->options->noTestTokens) {
                $token = null;
                $uniqueToken = null;
            } else {
                $token = $i;
                $uniqueToken = \uniqid();
            }
            $worker->start($wrapper, $token, $uniqueToken, [], $this->options);
            $this->streams[] = $worker->stdout();
            $this->workers[] = $worker;
        }
    }

    private function assignAllPendingTests()
    {
        $phpunit = $this->options->phpunit;
        $phpunitOptions = $this->options->filtered;
        // $phpunitOptions['no-globals-backup'] = null;  // removed in phpunit 6.0
        while (\count($this->pending)) {
            $this->waitForStreamsToChange($this->streams);
            foreach ($this->progressedWorkers() as $key => $worker) {
                if ($worker->isFree()) {
                    try {
                        $this->flushWorker($worker);
                        $pending = \array_shift($this->pending);
                        if ($pending) {
                            $worker->assign($pending, $phpunit, $phpunitOptions, $this->options);
                        }
                    } catch (\Exception $e) {
                        if ($this->options->verbose) {
                            $worker->stop();
                            echo "Error while assigning pending tests for worker $key: {$e->getMessage()}" . PHP_EOL;
                            echo $worker->getCrashReport();
                        }
                        throw $e;
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
        while (\count($toStop) > 0) {
            $toCheck = $this->streamsOf($toStop);
            $new = $this->waitForStreamsToChange($toCheck);
            foreach ($this->progressedWorkers() as $index => $worker) {
                try {
                    if (!$worker->isRunning()) {
                        $this->flushWorker($worker);
                        unset($toStop[$index]);
                    }
                } catch (\Exception $e) {
                    if ($this->options->verbose) {
                        $worker->stop();
                        unset($toStop[$index]);
                        echo "Error while waiting to finish for worker $index: {$e->getMessage()}" . PHP_EOL;
                        echo $worker->getCrashReport();
                    }
                    throw $e;
                }
            }
        }
    }

    // put on WorkersPool
    private function waitForStreamsToChange(array $modified)
    {
        $write = [];
        $except = [];
        $result = \stream_select($modified, $write, $except, 1);
        if ($result === false) {
            throw new \RuntimeException('stream_select() returned an error while waiting for all workers to finish.');
        }
        $this->modified = $modified;

        return $result;
    }

    /**
     * put on WorkersPool.
     *
     * @return WrapperWorker[]
     */
    private function progressedWorkers(): array
    {
        $result = [];
        foreach ($this->modified as $modifiedStream) {
            $found = null;
            foreach ($this->streams as $index => $stream) {
                if ($modifiedStream === $stream) {
                    $found = $index;
                    break;
                }
            }
            $result[$found] = $this->workers[$found];
        }
        $this->modified = [];

        return $result;
    }

    /**
     * Returns the output streams of a subset of workers.
     *
     * @param array    keys are positions in $this->workers
     *
     * @return array
     */
    private function streamsOf(array $workers): array
    {
        $streams = [];
        foreach (\array_keys($workers) as $index) {
            $streams[$index] = $this->streams[$index];
        }

        return $streams;
    }

    protected function complete()
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

    private function flushWorker(WrapperWorker $worker)
    {
        if ($this->hasCoverage()) {
            $this->getCoverage()->addCoverageFromFile($worker->getCoverageFileName());
        }
        $worker->printFeedback($this->printer);
        $worker->reset();
    }

    /*
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
