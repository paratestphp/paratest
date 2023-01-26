<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use ParaTest\Logging\JUnit\Reader;
use ParaTest\Runners\PHPUnit\EmptyLogFileException;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;
use ParaTest\Runners\PHPUnit\WorkerCrashedException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

use function array_map;
use function array_merge;
use function assert;
use function clearstatcache;
use function dirname;
use function end;
use function filesize;
use function implode;
use function realpath;
use function serialize;
use function sprintf;
use function touch;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;

/** @internal */
final class WrapperWorker
{
    public const COMMAND_EXIT = "EXIT\n";

    private ?ExecutableTest $currentlyExecuting = null;
    private Process $process;
    private int $inExecution = 0;
    private OutputInterface $output;
    private InputStream $input;
    private int $exitCode = -1;
    private string $statusFilepath;
    private string $progressFilepath;
    private string $junitFilepath;
    private ?string $coverageFilepath = null;
    private ?string $teamcityFilepath = null;

    public function __construct(OutputInterface $output, Options $options, int $token)
    {
        $wrapper = realpath(
            dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit-wrapper.php',
        );
        assert($wrapper !== false);

        $this->output = $output;

        $commonTmpFilePath = sprintf(
            '%s%sworker_%s_stdout_%s_',
            $options->tmpDir(),
            DIRECTORY_SEPARATOR,
            $token,
            uniqid(),
        );
        $this->statusFilepath = $commonTmpFilePath.'status';
        touch($this->statusFilepath);
        $this->progressFilepath = $commonTmpFilePath.'progress';
        touch($this->progressFilepath);
        $this->junitFilepath = $commonTmpFilePath.'junit';
        if ($options->hasCoverage()) {
            $this->coverageFilepath = $commonTmpFilePath.'coverage';
        }
        if ($options->needsTeamcity()) {
            $this->teamcityFilepath = $commonTmpFilePath.'teamcity';
        }

        $phpFinder = new PhpExecutableFinder();
        $phpBin    = $phpFinder->find(false);
        assert($phpBin !== false);
        $parameters = [$phpBin];
        $parameters = array_merge($parameters, $phpFinder->findArguments());

        if (($passthruPhp = $options->passthruPhp()) !== null) {
            $parameters = array_merge($parameters, $passthruPhp);
        }

        $parameters[] = $wrapper;
        $parameters[] = '--status-file';
        $parameters[] = $this->statusFilepath;
        $parameters[] = '--progress-file';
        $parameters[] = $this->progressFilepath;

        $phpunitArguments = [$options->phpunit()];
        $phpunitArguments[] = '--do-not-cache-result';
        $phpunitArguments[] = '--no-logging';
        $phpunitArguments[] = '--no-coverage';
        $phpunitArguments[] = '--no-output';
        $phpunitArguments[] = '--log-junit';
        $phpunitArguments[] = $this->junitFilepath;
        if (null !== $this->coverageFilepath) {
            $phpunitArguments[] = '--coverage-php';
            $phpunitArguments[] = $this->coverageFilepath;
        }
        if (null !== $this->teamcityFilepath) {
            $phpunitArguments[] = '--log-teamcity';
            $phpunitArguments[] = $this->teamcityFilepath;
        }
        if (null !== ($passthru = $options->passthru())) {
            $phpunitArguments = array_merge($phpunitArguments, $passthru);
        }
        
        $parameters[] = '--phpunit-argv';
        $parameters[] = serialize($phpunitArguments);

        if ($options->debug()) {
            $this->output->write(sprintf(
                "Starting WrapperWorker via: %s\n",
                implode(' ', array_map('\\escapeshellarg', $parameters)),
            ));
        }

        $this->input   = new InputStream();
        $this->process = new Process(
            $parameters,
            $options->cwd(),
            $options->fillEnvWithTokens($token),
            $this->input,
            null,
        );
    }

//    public function __destruct()
//    {
//        @unlink($this->statusFilepath);
//        @unlink($this->progressFilepath);
//    }

    public function start(): void
    {
        $this->process->start();
    }

    public function getWorkerCrashedException(?Throwable $previousException = null): WorkerCrashedException
    {
        return WorkerCrashedException::fromProcess(
            $this->process,
            $this->currentlyExecuting?->getPath() ?? 'N.A.',
            $previousException
        );
    }

    public function assign(ExecutableTest $test): void
    {
        assert($this->currentlyExecuting === null);

        $this->input->write($test->getPath() . "\n");
        $this->currentlyExecuting = $test;
        ++$this->inExecution;
    }

    public function printFeedback(ResultPrinter $printer): void
    {
        if ($this->currentlyExecuting === null) {
            return;
        }

        $feedbackContent = file_get_contents($this->progressFilepath);
        assert(is_string($feedbackContent) && '' !== $feedbackContent);
        $feedbackContent = preg_replace('/[^.SIRWFE]/', '', $feedbackContent);
        $testCount = $this->currentlyExecuting->getTestCount();

        $printer->printFeedback(substr($feedbackContent, -$testCount));
    }

    public function reset(): void
    {
        $this->currentlyExecuting = null;
    }

    public function stop(): void
    {
        $this->input->write(self::COMMAND_EXIT);
    }

    public function isFree(): bool
    {
        clearstatcache(true, $this->statusFilepath);

        $isFree = $this->inExecution === filesize($this->statusFilepath);
        
        if ($isFree && $this->inExecution > 0) {
            $exitCodes = file_get_contents($this->statusFilepath);
            assert(is_string($exitCodes) && '' !== $exitCodes);
            $this->exitCode = (int) $exitCodes[-1];
        }

        return $isFree;
    }
    
    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }
}
