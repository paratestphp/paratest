<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use Exception;
use ParaTest\Coverage\EmptyCoverageFileException;
use ParaTest\Runners\PHPUnit\Worker\RunnerWorker;
use PHPUnit\TextUI\TestRunner;

use function array_merge;
use function array_shift;
use function assert;
use function count;
use function getenv;
use function range;
use function usleep;

/**
 * @internal
 */
final class Runner extends BaseRunner
{
    /**
     * A collection of ExecutableTest objects that have processes
     * currently running.
     *
     * @var RunnerWorker[]
     */
    private $running = [];

    /**
     * The money maker. Runs all ExecutableTest objects in separate processes.
     */
    protected function doRun(): void
    {
        $availableTokens = range(1, $this->options->processes());
        while (count($this->running) > 0 || count($this->pending) > 0) {
            $this->fillRunQueue($availableTokens);
            usleep(10000);

            $availableTokens = [];
            foreach ($this->running as $token => $test) {
                if ($this->testIsStillRunning($test)) {
                    continue;
                }

                unset($this->running[$token]);
                $availableTokens[] = $token;
            }
        }
    }

    /**
     * This method removes ExecutableTest objects from the pending collection
     * and adds them to the running collection. It is also in charge of recycling and
     * acquiring available test tokens for use.
     *
     * @param int[] $availableTokens
     */
    private function fillRunQueue(array $availableTokens): void
    {
        while (
            count($this->pending) > 0
            && count($this->running) < $this->options->processes()
            && ($token = array_shift($availableTokens)) !== null
        ) {
            $env = array_merge(getenv(), $this->options->fillEnvWithTokens($token));

            $executebleTest = array_shift($this->pending);
            /** @psalm-suppress RedundantConditionGivenDocblockType **/
            assert($executebleTest !== null);

            $this->running[$token] = new RunnerWorker($executebleTest);
            $this->running[$token]->run(
                $this->options->phpunit(),
                $this->options->filtered(),
                $env,
                $this->options->passthru(),
                $this->options->passthruPhp(),
                $this->options->cwd()
            );

            if ($this->options->verbose() === 0) {
                continue;
            }

            $cmd = $this->running[$token];
            $this->output->write("\nExecuting test via: {$cmd->getExecutableTest()->getLastCommand()}\n");
        }
    }

    /**
     * Returns whether or not a test has finished being
     * executed. If it has, this method also halts a test process - optionally
     * throwing an exception if a fatal error has occurred -
     * prints feedback, and updates the overall exit code.
     *
     * @throws Exception
     */
    private function testIsStillRunning(RunnerWorker $worker): bool
    {
        if (! $worker->isDoneRunning()) {
            return true;
        }

        $this->setExitCode($worker);
        $worker->stop();
        if ($this->options->stopOnFailure() && $worker->getExitCode() > 0) {
            $this->pending = [];
        }

        $executableTest = $worker->getExecutableTest();
        if (
            $worker->getExitCode() > 0
            && $worker->getExitCode() !== TestRunner::FAILURE_EXIT
            && $worker->getExitCode() !== TestRunner::EXCEPTION_EXIT
        ) {
            throw new WorkerCrashedException($worker->getCrashReport());
        }

        if ($this->hasCoverage()) {
            $coverageMerger = $this->getCoverage();
            assert($coverageMerger !== null);
            try {
                $coverageMerger->addCoverageFromFile($executableTest->getCoverageFileName());
            } catch (EmptyCoverageFileException $emptyCoverageFileException) {
                throw new WorkerCrashedException($worker->getCrashReport(), 0, $emptyCoverageFileException);
            }
        }

        try {
            $this->printer->printFeedback($executableTest);
        } catch (EmptyLogFileException $emptyLogFileException) {
            throw new WorkerCrashedException($worker->getCrashReport(), 0, $emptyLogFileException);
        }

        return false;
    }

    /**
     * If the provided test object has an exit code
     * higher than the currently set exit code, that exit
     * code will be set as the overall exit code.
     */
    private function setExitCode(RunnerWorker $test): void
    {
        $exit = $test->getExitCode();
        if ($exit === null || $exit <= $this->exitcode) {
            return;
        }

        $this->exitcode = $exit;
    }

    protected function beforeLoadChecks(): void
    {
    }
}
