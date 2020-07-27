<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use Exception;
use Habitat\Habitat;
use Throwable;

use function array_filter;
use function array_keys;
use function array_shift;
use function count;
use function sprintf;
use function uniqid;
use function usleep;

use const PHP_EOL;

class Runner extends BaseRunner
{
    private const PHPUNIT_FATAL_ERROR = 255;

    /**
     * A collection of available tokens based on the number
     * of processes specified in $options.
     *
     * @var array<int, array<string, int|string|bool>>
     */
    protected $tokens = [];

    /**
     * {@inheritDoc}
     */
    public function __construct(array $opts = [])
    {
        parent::__construct($opts);
        $this->initTokens();
    }

    /**
     * The money maker. Runs all ExecutableTest objects in separate processes.
     */
    public function run(): void
    {
        parent::run();

        while (count($this->running) || count($this->pending)) {
            foreach ($this->running as $key => $test) {
                try {
                    if (! $this->testIsStillRunning($test)) {
                        unset($this->running[$key]);
                        $this->releaseToken($key);
                    }
                } catch (Throwable $e) {
                    if ($this->options->verbose) {
                        echo "An error for $key: {$e->getMessage()}" . PHP_EOL;
                        echo "Command: {$test->getLastCommand()}" . PHP_EOL;
                        echo 'StdErr: ' . $test->getStderr() . PHP_EOL;
                        echo 'StdOut: ' . $test->getStdout() . PHP_EOL;
                    }

                    throw $e;
                }
            }

            $this->fillRunQueue();
            usleep(10000);
        }

        $this->complete();
    }

    /**
     * Finalizes the run process. This method
     * prints all results, rewinds the log interpreter,
     * logs any results to JUnit, and cleans up temporary
     * files.
     */
    private function complete(): void
    {
        $this->printer->printResults();
        $this->interpreter->rewind();
        $this->log();
        $this->logCoverage();
        $readers = $this->interpreter->getReaders();
        foreach ($readers as $reader) {
            $reader->removeLog();
        }
    }

    /**
     * This method removes ExecutableTest objects from the pending collection
     * and adds them to the running collection. It is also in charge of recycling and
     * acquiring available test tokens for use.
     */
    private function fillRunQueue(): void
    {
        $opts = $this->options;
        while (count($this->pending) && count($this->running) < $opts->processes) {
            $tokenData = $this->getNextAvailableToken();
            if ($tokenData === false) {
                continue;
            }

            $this->acquireToken($tokenData['token']);
            $env                                = [
                'TEST_TOKEN' => $tokenData['token'],
                'UNIQUE_TEST_TOKEN' => $tokenData['unique'],
            ] + Habitat::getAll();
            $this->running[$tokenData['token']] = array_shift($this->pending)
                ->run($opts->phpunit, $opts->filtered, $env, $opts->passthru, $opts->passthruPhp);
            if (! $opts->verbose) {
                continue;
            }

            $cmd = $this->running[$tokenData['token']];
            echo "\nExecuting test via: {$cmd->getLastCommand()}\n";
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
    private function testIsStillRunning(ExecutableTest $test): bool
    {
        if (! $test->isDoneRunning()) {
            return true;
        }

        $this->setExitCode($test);
        $test->stop();
        if ($this->options->stopOnFailure && $test->getExitCode() > 0) {
            $this->pending = [];
        }

        if ($test->getExitCode() === self::PHPUNIT_FATAL_ERROR) {
            $errorOutput = $test->getStderr();
            if (! $errorOutput) {
                $errorOutput = $test->getStdout();
            }

            throw new Exception(sprintf("Fatal error in %s:\n%s", $test->getPath(), $errorOutput));
        }

        $this->printer->printFeedback($test);
        if ($this->hasCoverage()) {
            $this->addCoverage($test);
        }

        return false;
    }

    /**
     * If the provided test object has an exit code
     * higher than the currently set exit code, that exit
     * code will be set as the overall exit code.
     */
    private function setExitCode(ExecutableTest $test): void
    {
        $exit = $test->getExitCode();
        if ($exit <= $this->exitcode) {
            return;
        }

        $this->exitcode = $exit;
    }

    /**
     * Initialize the available test tokens based
     * on how many processes ParaTest will be run in.
     */
    protected function initTokens(): void
    {
        $this->tokens = [];
        for ($i = 1; $i <= $this->options->processes; ++$i) {
            $this->tokens[$i] = ['token' => $i, 'unique' => uniqid(sprintf('%s_', $i)), 'available' => true];
        }
    }

    /**
     * Gets the next token that is available to be acquired
     * from a finished process.
     *
     * @return bool|array<string, mixed>
     */
    protected function getNextAvailableToken()
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
    protected function releaseToken(int $tokenIdentifier): void
    {
        $filtered = array_filter($this->tokens, static function ($val) use ($tokenIdentifier) {
            return $val['token'] === $tokenIdentifier;
        });

        $keys = array_keys($filtered);

        $this->tokens[$keys[0]]['available'] = true;
    }

    /**
     * Flag a token as acquired and not available for use.
     */
    protected function acquireToken(int $tokenIdentifier): void
    {
        $filtered = array_filter($this->tokens, static function ($val) use ($tokenIdentifier) {
            return $val['token'] === $tokenIdentifier;
        });

        $keys = array_keys($filtered);

        $this->tokens[$keys[0]]['available'] = false;
    }

    private function addCoverage(ExecutableTest $test): void
    {
        $coverageFile = $test->getCoverageFileName();
        $this->getCoverage()->addCoverageFromFile($coverageFile);
    }
}
