<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use Exception;
use ParaTest\Runners\PHPUnit\Configuration;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;
use RuntimeException;

use function fgets;
use function fwrite;
use function implode;
use function proc_get_status;
use function serialize;
use function stream_set_blocking;
use function strstr;

class WrapperWorker extends BaseWorker
{
    /** @var string[] */
    private $commands = [];

    /** @var ExecutableTest|null */
    private $currentlyExecuting;

    /**
     * @return resource
     */
    public function stdout()
    {
        return $this->pipes[1];
    }

    /**
     * @param string[] $testCmdArguments
     */
    public function execute(array $testCmdArguments): void
    {
        $this->checkStarted();
        $this->commands[] = implode(' ', $testCmdArguments);
        fwrite($this->pipes[0], serialize($testCmdArguments) . "\n");
        ++$this->inExecution;
    }

    /**
     * @param array<string, (string|bool|int|Configuration|string[]|null)> $phpunitOptions
     */
    public function assign(ExecutableTest $test, string $phpunit, array $phpunitOptions, Options $options): void
    {
        if ($this->currentlyExecuting !== null) {
            throw new Exception('Worker already has a test assigned - did you forget to call reset()?');
        }

        $this->currentlyExecuting = $test;
        $commandArguments         = $test->commandArguments($phpunit, $phpunitOptions, $options->passthru);
        $command                  = implode(' ', $commandArguments);
        if ($options->verbose) {
            echo "\nExecuting test via: $command\n";
        }

        $test->setLastCommand($command);
        $this->execute($commandArguments);
    }

    public function printFeedback(ResultPrinter $printer): void
    {
        if ($this->currentlyExecuting === null) {
            return;
        }

        $printer->printFeedback($this->currentlyExecuting);
    }

    public function reset(): void
    {
        $this->currentlyExecuting = null;
    }

    protected function checkStarted(): void
    {
        if (! $this->isStarted()) {
            throw new RuntimeException('You have to start the Worker first!');
        }
    }

    public function stop(): void
    {
        fwrite($this->pipes[0], "EXIT\n");
        parent::stop();
    }

    /**
     * This is an utility function for tests.
     * Refactor or write it only in the test case.
     */
    public function waitForFinishedJob(): void
    {
        if ($this->inExecution === 0) {
            return;
        }

        $tellsUsItHasFinished = false;
        stream_set_blocking($this->pipes[1], true);
        while ($line = fgets($this->pipes[1])) {
            if (strstr($line, "FINISHED\n")) {
                $tellsUsItHasFinished = true;
                --$this->inExecution;
                break;
            }
        }

        if (! $tellsUsItHasFinished) {
            throw new RuntimeException('The Worker terminated without finishing the job.');
        }
    }

    /**
     * @deprecated
     * This function consumes a lot of CPU while waiting for
     * the worker to finish. Use it only in testing paratest
     * itself.
     */
    public function waitForStop(): void
    {
        $status = proc_get_status($this->proc);
        while ($status['running']) {
            $status = proc_get_status($this->proc);
            $this->setExitCode($status);
        }
    }

    public function getCoverageFileName(): ?string
    {
        if ($this->currentlyExecuting !== null) {
            return $this->currentlyExecuting->getCoverageFileName();
        }

        return null;
    }
}
