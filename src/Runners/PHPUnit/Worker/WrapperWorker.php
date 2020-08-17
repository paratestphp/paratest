<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;
use RuntimeException;

use function assert;
use function fgets;
use function fwrite;
use function implode;
use function proc_get_status;
use function serialize;
use function stream_set_blocking;
use function strstr;

final class WrapperWorker extends BaseWorker
{
    /** @var ExecutableTest|null */
    private $currentlyExecuting;

    /**
     * {@inheritDoc}
     */
    protected function configureParameters(array &$parameters): void
    {
    }

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
     * @param array<string, string> $phpunitOptions
     */
    public function assign(ExecutableTest $test, string $phpunit, array $phpunitOptions, Options $options): void
    {
        if ($this->currentlyExecuting !== null) {
            throw new RuntimeException('Worker already has a test assigned - did you forget to call reset()?');
        }

        $this->currentlyExecuting = $test;
        $commandArguments         = $test->commandArguments($phpunit, $phpunitOptions, $options->passthru());
        $command                  = implode(' ', $commandArguments);
        if ($options->verbose() > 0) {
            $this->output->write("\nExecuting test via: {$command}\n");
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

    private function checkStarted(): void
    {
        if (! $this->isStarted()) {
            throw new RuntimeException('You have to start the Worker first!');
        }
    }

    protected function doStop(): void
    {
        fwrite($this->pipes[0], "EXIT\n");
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
            if (strstr($line, "FINISHED\n") !== false) {
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
     * @internal
     *
     * This function consumes a lot of CPU while waiting for
     * the worker to finish. Use it only in testing paratest
     * itself.
     */
    public function waitForStop(): void
    {
        assert($this->proc !== null);
        $status = proc_get_status($this->proc);
        assert($status !== false);
        while ($status['running']) {
            $status = proc_get_status($this->proc);
            assert($status !== false);
            $this->setExitCode($status['running'], $status['exitcode']);
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
