<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use Exception;
use Habitat\Habitat;
use ParaTest\Runners\PHPUnit\Worker\RunnerWorker;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_filter;
use function array_keys;
use function array_shift;
use function count;
use function sprintf;
use function uniqid;
use function usleep;

final class Runner extends BaseRunner
{
    private const PHPUNIT_FATAL_ERROR = 255;

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
     * @var array<int, array{token: int, unique: string, available: bool}>
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
    public function run(): void
    {
        $this->initialize();

        while (count($this->running) > 0 || count($this->pending) > 0) {
            foreach ($this->running as $key => $test) {
                try {
                    if (! $this->testIsStillRunning($test)) {
                        unset($this->running[$key]);
                        $this->releaseToken($key);
                    }
                } catch (Throwable $e) {
                    if ($this->options->verbose() > 0) {
                        $this->output->writeln("An error for $key: {$e->getMessage()}");
                        $this->output->writeln("Command: {$test->getExecutableTest()->getLastCommand()}");
                        $this->output->writeln('StdErr: ' . $test->getStderr());
                        $this->output->writeln('StdOut: ' . $test->getStdout());
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
        while (count($this->pending) > 0 && count($this->running) < $this->options->processes()) {
            $tokenData = $this->getNextAvailableToken();
            if ($tokenData === false) {
                continue;
            }

            $this->acquireToken($tokenData['token']);
            $env = [];
            if (! $this->options->noTestTokens()) {
                $env = [
                    'TEST_TOKEN' => $tokenData['token'],
                    'UNIQUE_TEST_TOKEN' => $tokenData['unique'],
                ];
            }

            $env += Habitat::getAll();

            $executebleTest                     = array_shift($this->pending);
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
        if ($worker->getExitCode() === self::PHPUNIT_FATAL_ERROR) {
            $errorOutput = $worker->getStderr();
            if ($errorOutput === '') {
                $errorOutput = $worker->getStdout();
            }

            throw new RuntimeException(sprintf("Fatal error in %s:\n%s", $executableTest->getPath(), $errorOutput));
        }

        $this->printer->printFeedback($executableTest);
        if ($this->hasCoverage()) {
            $this->addCoverage($executableTest);
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
        if ($exit <= $this->exitcode) {
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
            $this->tokens[$i] = ['token' => $i, 'unique' => uniqid(sprintf('%s_', $i)), 'available' => true];
        }
    }

    /**
     * Gets the next token that is available to be acquired
     * from a finished process.
     *
     * @return false|array{token: int, unique: string, available: bool}
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

    private function addCoverage(ExecutableTest $test): void
    {
        $coverageFile = $test->getCoverageFileName();
        $this->getCoverage()->addCoverageFromFile($coverageFile);
    }

    protected function beforeLoadChecks(): void
    {
    }
}
