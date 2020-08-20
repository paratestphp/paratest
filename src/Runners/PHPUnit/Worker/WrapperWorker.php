<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;
use RuntimeException;

use function array_map;
use function assert;
use function fclose;
use function fgets;
use function fwrite;
use function implode;
use function serialize;
use function stream_set_blocking;
use function strstr;

final class WrapperWorker extends BaseWorker
{
    public const COMMAND_EXIT     = "EXIT\n";
    public const COMMAND_FINISHED = "FINISHED\n";

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
        $this->commands[] = implode(' ', array_map('escapeshellarg', $testCmdArguments));
        fwrite($this->pipes[0], serialize($testCmdArguments) . "\n");
        ++$this->inExecution;
    }

    /**
     * @param array<string, string|null> $phpunitOptions
     */
    public function assign(ExecutableTest $test, string $phpunit, array $phpunitOptions, Options $options): void
    {
        assert($this->currentlyExecuting === null);
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

    public function stop(): void
    {
        fwrite($this->pipes[0], self::COMMAND_EXIT);
        fclose($this->pipes[0]);
    }

    /**
     * @internal
     *
     * @codeCoverageIgnore
     *
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
            if (strstr($line, self::COMMAND_FINISHED) !== false) {
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
     * @codeCoverageIgnore
     *
     * This function consumes a lot of CPU while waiting for
     * the worker to finish. Use it only in testing paratest
     * itself.
     */
    public function waitForStop(): void
    {
        do {
            $this->updateProcStatus();
        } while ($this->running);
    }

    public function getCoverageFileName(): ?string
    {
        if ($this->currentlyExecuting !== null) {
            return $this->currentlyExecuting->getCoverageFileName();
        }

        return null;
    }
}
