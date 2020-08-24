<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Coverage\EmptyCoverageFileException;
use ParaTest\Runners\PHPUnit\Worker\WrapperWorker;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;
use function array_shift;
use function assert;
use function count;
use function defined;
use function dirname;
use function realpath;
use function stream_select;

use const DIRECTORY_SEPARATOR;

/**
 * @internal
 */
final class WrapperRunner extends BaseWrapperRunner
{
    /** @var WrapperWorker[] */
    private $workers = [];

    /** @var resource[] */
    private $streams = [];

    /** @var resource[] */
    private $modified = [];

    public function __construct(Options $opts, OutputInterface $output)
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            throw new RuntimeException('WrapperRunner is not supported on Windows'); // @codeCoverageIgnore
        }

        parent::__construct($opts, $output);
    }

    protected function doRun(): void
    {
        $this->startWorkers();
        $this->assignAllPendingTests();
        $this->sendStopMessages();
        $this->waitForAllToFinish();
    }

    private function startWorkers(): void
    {
        $wrapper = realpath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit-wrapper.php'
        );
        assert($wrapper !== false);
        for ($i = 1; $i <= $this->options->processes(); ++$i) {
            $worker = new WrapperWorker($this->output);

            $worker->start($wrapper, $this->options, $i);
            $this->streams[] = $worker->stdout();
            $this->workers[] = $worker;
        }
    }

    private function assignAllPendingTests(): void
    {
        $phpunit        = $this->options->phpunit();
        $phpunitOptions = $this->options->filtered();

        while (count($this->pending) > 0 && count($this->workers) > 0) {
            $this->waitForStreamsToChange($this->streams);
            foreach ($this->progressedWorkers() as $key => $worker) {
                if (! $worker->isRunning()) {
                    $this->setExitCode($worker->getExitCode());
                    unset($this->workers[$key]);
                    if ($this->options->stopOnFailure()) {
                        $this->pending = [];
                    }

                    continue;
                }

                if (! $worker->isFree()) {
                    // Happens randomly depending on concurrency and resource usage
                    // Cannot be covered by tests reliably
                    continue; // @codeCoverageIgnore
                }

                $this->flushWorker($worker);
                $pending = array_shift($this->pending);
                if ($pending === null) {
                    // Happens randomly depending on concurrency and resource usage
                    // Cannot be covered by tests reliably
                    continue; // @codeCoverageIgnore
                }

                $worker->assign($pending, $phpunit, $phpunitOptions, $this->options);
            }
        }
    }

    /**
     * put on WorkersPool
     *
     * @param resource[] $modified
     */
    private function waitForStreamsToChange(array $modified): void
    {
        $write  = [];
        $except = [];
        $result = stream_select($modified, $write, $except, 1);
        assert($result !== false);

        $this->modified = $modified;
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
            if (($coverageFileName = $worker->getCoverageFileName()) !== null) {
                try {
                    $coverageMerger->addCoverageFromFile($coverageFileName);
                } catch (EmptyCoverageFileException $emptyCoverageFileException) {
                    throw new WorkerCrashedException($worker->getCrashReport(), 0, $emptyCoverageFileException);
                }
            }
        }

        try {
            $worker->printFeedback($this->printer);
        } catch (EmptyLogFileException $emptyLogFileException) {
            throw new WorkerCrashedException($worker->getCrashReport(), 0, $emptyLogFileException);
        }

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
            $this->waitForStreamsToChange($toCheck);
            foreach ($this->progressedWorkers() as $index => $worker) {
                if ($worker->isRunning()) {
                    continue;
                }

                $this->flushWorker($worker);
                $this->setExitCode($worker->getExitCode());
                unset($toStop[$index]);
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
