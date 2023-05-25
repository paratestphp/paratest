<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\Worker\WrapperWorker;
use PHPUnit\TextUI\TestRunner;

use function array_shift;
use function assert;
use function count;
use function max;
use function usleep;

/** @internal */
final class WrapperRunner extends BaseRunner
{
    /** @var array<int,WrapperWorker> */
    private $workers = [];

    /** @var array<int,int> */
    private $batches = [];

    protected function beforeLoadChecks(): void
    {
        if ($this->options->functional()) {
            throw new InvalidArgumentException(
                'The `functional` option is not supported yet in the WrapperRunner. Only full classes can be run due ' .
                'to the current PHPUnit commands causing classloading issues.',
            );
        }
    }

    protected function doRun(): void
    {
        $this->startWorkers();
        $this->assignAllPendingTests();
        $this->waitForAllToFinish();
    }

    private function startWorkers(): void
    {
        for ($token = 1; $token <= $this->options->processes(); ++$token) {
            $this->startWorker($token);
        }
    }

    private function assignAllPendingTests(): void
    {
        $phpunit        = $this->options->phpunit();
        $phpunitOptions = $this->options->filtered();
        $batchSize      = $this->options->maxBatchSize();

        while (count($this->pending) > 0 && count($this->workers) > 0) {
            foreach ($this->workers as $token => $worker) {
                if (! $worker->isRunning()) {
                    throw $worker->getWorkerCrashedException();
                }

                if (! $worker->isFree()) {
                    continue;
                }

                $this->flushWorker($worker);

                if ($batchSize !== null && $batchSize !== 0 && $this->batches[$token] === $batchSize) {
                    $this->destroyWorker($token);
                    $worker = $this->startWorker($token);
                }

                if ($this->exitcode > 0 && ($this->options->stopOnFailure() || $this->options->stopOnError())) {
                    $this->pending = [];
                } elseif (($pending = array_shift($this->pending)) !== null) {
                    $worker->assign($pending, $phpunit, $phpunitOptions, $this->options);
                    $this->batches[$token]++;
                }
            }

            usleep(self::CYCLE_SLEEP);
        }
    }

    private function flushWorker(WrapperWorker $worker): void
    {
        $reader = $worker->printFeedback($this->printer);

        if ($this->hasCoverage()) {
            $coverageMerger = $this->getCoverage();
            assert($coverageMerger !== null);
            if (($coverageFileName = $worker->getCoverageFileName()) !== null) {
                $coverageMerger->addCoverageFromFile($coverageFileName);
            }
        }

        $worker->reset();

        if ($reader === null) {
            return;
        }

        $exitCode = TestRunner::SUCCESS_EXIT;
        if ($reader->getTotalErrors() > 0) {
            $exitCode = TestRunner::EXCEPTION_EXIT;
        } elseif ($reader->getTotalFailures() > 0 || $reader->getTotalWarnings() > 0) {
            $exitCode = TestRunner::FAILURE_EXIT;
        }

        $this->exitcode = max($this->exitcode, $exitCode);
    }

    private function waitForAllToFinish(): void
    {
        $stopped = [];
        while (count($this->workers) > 0) {
            foreach ($this->workers as $index => $worker) {
                if ($worker->isRunning()) {
                    if (! isset($stopped[$index]) && $worker->isFree()) {
                        $worker->stop();
                        $stopped[$index] = true;
                    }

                    continue;
                }

                if (! $worker->isFree()) {
                    throw $worker->getWorkerCrashedException();
                }

                $this->flushWorker($worker);
                unset($this->workers[$index]);
            }

            usleep(self::CYCLE_SLEEP);
        }
    }

    private function startWorker(int $token): WrapperWorker
    {
        $this->workers[$token] = new WrapperWorker($this->output, $this->options, $token);
        $this->workers[$token]->start();
        $this->batches[$token] = 0;

        return $this->workers[$token];
    }

    private function destroyWorker(int $token): void
    {
        // Mutation Testing tells us that the following `unset()` already destroys
        // the `WrapperWorker`, which destroys the Symfony's `Process`, which
        // automatically calls `Process::stop` within `Process::__destruct()`.
        // But we prefer to have an explicit stops.
        $this->workers[$token]->stop();

        unset($this->workers[$token]);
    }
}
