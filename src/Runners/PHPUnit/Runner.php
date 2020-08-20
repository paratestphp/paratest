<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use Exception;
use ParaTest\Coverage\EmptyCoverageFileException;
use ParaTest\Runners\PHPUnit\Worker\RunnerWorker;
use PHPUnit\TextUI\TestRunner;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_keys;
use function array_merge;
use function array_shift;
use function assert;
use function count;
use function getenv;
use function usleep;

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
     * A collection of available tokens based on the number
     * of processes specified in $options.
     *
     * @var array<int, array{token: int, available: bool}>
     */
    private $tokens = [];

    public function __construct(Options $opts, OutputInterface $output)
    {
        parent::__construct($opts, $output);
        $this->initTokens();
    }

    /**
     * The money maker. Runs all ExecutableTest objects in separate processes.
     */
    protected function doRun(): void
    {
        while (count($this->running) > 0 || count($this->pending) > 0) {
            foreach ($this->running as $key => $test) {
                if ($this->testIsStillRunning($test)) {
                    continue;
                }

                unset($this->running[$key]);
                $this->releaseToken($key);
            }

            $this->fillRunQueue();
            usleep(10000);
        }
    }

    /**
     * This method removes ExecutableTest objects from the pending collection
     * and adds them to the running collection. It is also in charge of recycling and
     * acquiring available test tokens for use.
     */
    private function fillRunQueue(): void
    {
        while (
            count($this->pending) > 0
            && count($this->running) < $this->options->processes()
            && ($tokenData = $this->getNextAvailableToken()) !== false
        ) {
            $this->acquireToken($tokenData['token']);
            $env = array_merge(getenv(), $this->options->fillEnvWithTokens($tokenData['token']));

            $executebleTest = array_shift($this->pending);
            /** @psalm-suppress RedundantConditionGivenDocblockType **/
            assert($executebleTest !== null);

            $this->running[$tokenData['token']] = new RunnerWorker($executebleTest);
            $this->running[$tokenData['token']]->run(
                $this->options->phpunit(),
                $this->options->filtered(),
                $env,
                $this->options->passthru(),
                $this->options->passthruPhp()
            );

            if ($this->options->verbose() === 0) {
                continue;
            }

            $cmd = $this->running[$tokenData['token']];
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

    /**
     * Initialize the available test tokens based
     * on how many processes ParaTest will be run in.
     */
    private function initTokens(): void
    {
        $this->tokens = [];
        for ($i = 1; $i <= $this->options->processes(); ++$i) {
            $this->tokens[$i] = [
                'token' => $i,
                'available' => true,
            ];
        }
    }

    /**
     * Gets the next token that is available to be acquired
     * from a finished process.
     *
     * @return false|array{token: int, available: bool}
     */
    private function getNextAvailableToken()
    {
        foreach ($this->tokens as $data) {
            if ($data['available']) {
                return $data;
            }
        }

        return false;
    }

    /**
     * Flag a token as available for use.
     */
    private function releaseToken(int $tokenIdentifier): void
    {
        $filtered = array_filter($this->tokens, static function ($val) use ($tokenIdentifier): bool {
            return $val['token'] === $tokenIdentifier;
        });

        $keys = array_keys($filtered);

        $this->tokens[$keys[0]]['available'] = true;
    }

    /**
     * Flag a token as acquired and not available for use.
     */
    private function acquireToken(int $tokenIdentifier): void
    {
        $filtered = array_filter($this->tokens, static function ($val) use ($tokenIdentifier): bool {
            return $val['token'] === $tokenIdentifier;
        });

        $keys = array_keys($filtered);

        $this->tokens[$keys[0]]['available'] = false;
    }

    protected function beforeLoadChecks(): void
    {
    }
}
