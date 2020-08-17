<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Worker\WrapperWorker;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_keys;
use function array_shift;
use function assert;
use function count;
use function defined;
use function dirname;
use function realpath;
use function stream_select;
use function uniqid;

use const DIRECTORY_SEPARATOR;

final class WrapperRunner extends BaseWrapperRunner
{
    /** @var WrapperWorker[] */
    private $workers = [];

    public function __construct(Options $opts, OutputInterface $output)
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            throw new RuntimeException('WrapperRunner is not supported on Windows');
        }

        parent::__construct($opts, $output);
    }

    public function run(): void
    {
        $this->initialize();
        $this->startWorkers();
        $this->assignAllPendingTests();
        $this->sendStopMessages();
        $this->waitForAllToFinish();
        $this->complete();
    }

    private function startWorkers(): void
    {
        $wrapper = realpath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit-wrapper.php'
        );
        assert($wrapper !== false);
        for ($i = 1; $i <= $this->options->processes(); ++$i) {
            $worker = new WrapperWorker($this->output);
            if ($this->options->noTestTokens()) {
                $token       = null;
                $uniqueToken = null;
            } else {
                $token       = $i;
                $uniqueToken = uniqid();
            }

            $worker->start($wrapper, $token, $uniqueToken, [], $this->options);
            $this->streams[] = $worker->stdout();
            $this->workers[] = $worker;
        }
    }

    private function assignAllPendingTests(): void
    {
        $phpunit        = $this->options->phpunit();
        $phpunitOptions = $this->options->filtered();
        // $phpunitOptions['no-globals-backup'] = null;  // removed in phpunit 6.0
        while (count($this->pending)) {
            $this->waitForStreamsToChange($this->streams);
            foreach ($this->progressedWorkers() as $key => $worker) {
                if (! $worker->isFree()) {
                    continue;
                }

                try {
                    $this->flushWorker($worker);
                    $pending = array_shift($this->pending);
                    if ($pending !== null) {
                        $worker->assign($pending, $phpunit, $phpunitOptions, $this->options);
                    }
                } catch (Throwable $e) {
                    if ($this->options->verbose() > 0) {
                        $worker->stop();
                        $this->output->writeln(
                            "Error while assigning pending tests for worker {$key}: {$e->getMessage()}"
                        );
                        $this->output->write($worker->getCrashReport());
                    }

                    throw $e;
                }
            }
        }
    }

    /**
     * put on WorkersPool
     *
     * @param resource[] $modified
     */
    private function waitForStreamsToChange(array $modified): int
    {
        $write  = [];
        $except = [];
        $result = stream_select($modified, $write, $except, 1);
        if ($result === false) {
            throw new RuntimeException('stream_select() returned an error while waiting for all workers to finish.');
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

            assert($found !== null);

            $result[$found] = $this->workers[$found];
        }

        $this->modified = [];

        return $result;
    }

    private function flushWorker(WrapperWorker $worker): void
    {
        if ($this->hasCoverage()) {
            $coverageMerger = $this->getCoverage();
            assert($coverageMerger !== null);

            $coverageMerger->addCoverageFromFile($worker->getCoverageFileName());
        }

        $worker->printFeedback($this->printer);
        $worker->reset();
    }

    private function sendStopMessages(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }
    }

    private function waitForAllToFinish(): void
    {
        $toStop = $this->workers;
        while (count($toStop) > 0) {
            $toCheck = $this->streamsOf($toStop);
            $new     = $this->waitForStreamsToChange($toCheck);
            foreach ($this->progressedWorkers() as $index => $worker) {
                try {
                    if (! $worker->isRunning()) {
                        $this->flushWorker($worker);
                        unset($toStop[$index]);
                    }
                } catch (Throwable $e) {
                    if ($this->options->verbose() > 0) {
                        $worker->stop();
                        unset($toStop[$index]);
                        $this->output->writeln("Error while waiting to finish for worker {$index}: {$e->getMessage()}");
                        $this->output->write($worker->getCrashReport());
                    }

                    throw $e;
                }
            }
        }
    }

    /**
     * Returns the output streams of a subset of workers.
     *
     * @param WrapperWorker[] $workers keys are positions in $this->workers
     *
     * @return resource[]
     */
    private function streamsOf(array $workers): array
    {
        $streams = [];
        foreach (array_keys($workers) as $index) {
            $streams[$index] = $this->streams[$index];
        }

        return $streams;
    }
}
