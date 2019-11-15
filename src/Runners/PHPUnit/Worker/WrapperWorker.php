<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use Exception;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;

class WrapperWorker extends BaseWorker
{
    /**
     * @var string[]
     */
    private $commands = [];

    /**
     * @var ExecutableTest
     */
    private $currentlyExecuting;

    public function stdout()
    {
        return $this->pipes[1];
    }

    public function execute(string $testCmd)
    {
        $this->checkStarted();
        $this->commands[] = $testCmd;
        \fwrite($this->pipes[0], $testCmd . "\n");
        ++$this->inExecution;
    }

    public function assign(ExecutableTest $test, string $phpunit, array $phpunitOptions, Options $options)
    {
        if ($this->currentlyExecuting !== null) {
            throw new Exception('Worker already has a test assigned - did you forget to call reset()?');
        }
        $this->currentlyExecuting = $test;
        $command = $test->command($phpunit, $phpunitOptions, $options->passthru);
        if ($options->verbose) {
            echo "\nExecuting test via: $command\n";
        }
        $test->setLastCommand($command);
        $this->execute($command);
    }

    public function printFeedback(ResultPrinter $printer)
    {
        if ($this->currentlyExecuting !== null) {
            $printer->printFeedback($this->currentlyExecuting);
        }
    }

    public function reset()
    {
        $this->currentlyExecuting = null;
    }

    protected function checkStarted()
    {
        if (!$this->isStarted()) {
            throw new \RuntimeException('You have to start the Worker first!');
        }
    }

    public function stop()
    {
        \fwrite($this->pipes[0], "EXIT\n");
        parent::stop();
    }

    /**
     * This is an utility function for tests.
     * Refactor or write it only in the test case.
     */
    public function waitForFinishedJob()
    {
        if ($this->inExecution === 0) {
            return;
        }
        $tellsUsItHasFinished = false;
        \stream_set_blocking($this->pipes[1], true);
        while ($line = \fgets($this->pipes[1])) {
            if (\strstr($line, "FINISHED\n")) {
                $tellsUsItHasFinished = true;
                --$this->inExecution;
                break;
            }
        }
        if (!$tellsUsItHasFinished) {
            throw new \RuntimeException('The Worker terminated without finishing the job.');
        }
    }

    /**
     * @deprecated
     * This function consumes a lot of CPU while waiting for
     * the worker to finish. Use it only in testing paratest
     * itself.
     */
    public function waitForStop()
    {
        $status = \proc_get_status($this->proc);
        while ($status['running']) {
            $status = \proc_get_status($this->proc);
            $this->setExitCode($status);
        }
    }

    public function getCoverageFileName()
    {
        if ($this->currentlyExecuting !== null) {
            return $this->currentlyExecuting->getCoverageFileName();
        }
    }
}
