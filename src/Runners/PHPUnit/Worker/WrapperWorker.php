<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;

use function array_map;
use function assert;
use function clearstatcache;
use function fclose;
use function filesize;
use function fwrite;
use function implode;
use function serialize;

/**
 * @internal
 */
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
     * @param array<string, string|null> $phpunitOptions
     */
    public function assign(ExecutableTest $test, string $phpunit, array $phpunitOptions, Options $options): void
    {
        assert($this->currentlyExecuting === null);
        $phpunitOptions['printer'] = NullPhpunitPrinter::class;
        $commandArguments          = $test->commandArguments($phpunit, $phpunitOptions, $options->passthru());
        $command                   = implode(' ', array_map('\\escapeshellarg', $commandArguments));
        if ($options->verbose() > 0) {
            $this->output->write("\nExecuting test via: {$command}\n");
        }

        if (@fwrite($this->pipes[0], serialize($commandArguments) . "\n") === false) {
            // Happens when isFree returns true (the test ended) and also
            // isRunning returns true, but in the meanwhile due to a --stop-on-failure
            // the process exited
            return; // @codeCoverageIgnore
        }

        $this->currentlyExecuting = $test;
        $test->setLastCommand($command);
        $this->commands[] = $command;
        ++$this->inExecution;
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

    public function getCoverageFileName(): ?string
    {
        if ($this->currentlyExecuting !== null) {
            return $this->currentlyExecuting->getCoverageFileName();
        }

        return null;
    }

    public function isFree(): bool
    {
        $this->checkNotCrashed();

        clearstatcache(true, $this->writeToPathname);

        return $this->inExecution === filesize($this->writeToPathname);
    }

    public function isRunning(): bool
    {
        $this->checkNotCrashed();

        return $this->running;
    }
}
