<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use ParaTest\Logging\JUnit\Reader;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;
use ParaTest\Runners\PHPUnit\WorkerCrashedException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function array_map;
use function array_merge;
use function assert;
use function clearstatcache;
use function count;
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
use const PHP_EOL;

/**
 * @internal
 */
final class WrapperWorker
{
    /**
     * It must be a 1 byte string to ensure
     * filesize() is equal to the number of tests executed
     */
    public const TEST_EXECUTED_MARKER = '1';

    public const COMMAND_EXIT = "EXIT\n";

    /** @var ExecutableTest|null */
    private $currentlyExecuting;
    /** @var Process */
    private $process;
    /** @var int */
    private $inExecution = 0;
    /** @var OutputInterface */
    private $output;
    /** @var string[] */
    private $commands = [];
    /** @var string */
    private $writeToPathname;
    /** @var InputStream */
    private $input;

    public function __construct(OutputInterface $output, Options $options, int $token)
    {
        $wrapper = realpath(
            dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit-wrapper.php'
        );
        assert($wrapper !== false);

        $this->output = $output;

        $this->writeToPathname = sprintf(
            '%s%sworker_%s_stdout_%s',
            $options->tmpDir(),
            DIRECTORY_SEPARATOR,
            $token,
            uniqid()
        );
        touch($this->writeToPathname);

        $finder        = new PhpExecutableFinder();
        $phpExecutable = $finder->find();
        assert($phpExecutable !== false);

        $parameters = [$phpExecutable];
        if (($passthruPhp = $options->passthruPhp()) !== null) {
            $parameters = array_merge($parameters, $passthruPhp);
        }

        $parameters[] = $wrapper;

        if ($options->stopOnFailure()) {
            $parameters[] = '--stop-on-failure';
        }

        $parameters[] = '--write-to';
        $parameters[] = $this->writeToPathname;

        if ($options->verbose() > 0) {
            $this->output->writeln(sprintf(
                'Starting WrapperWorker via: %s',
                implode(' ', array_map('\escapeshellarg', $parameters))
            ));
        }

        $this->input   = new InputStream();
        $this->process = new Process(
            $parameters,
            $options->cwd(),
            $options->fillEnvWithTokens($token),
            $this->input,
            null
        );
    }

    public function __destruct()
    {
        @unlink($this->writeToPathname);
    }

    public function start(): void
    {
        $this->process->start();
    }

    public function raiseProcessFailedException(): void
    {
        throw new WorkerCrashedException(
            sprintf('Error executing: %s', end($this->commands)),
            0,
            new ProcessFailedException($this->process)
        );
    }

    public function getCrashReport(): string
    {
        $lastCommand = count($this->commands) !== 0 ? 'Last executed command: ' . end($this->commands) : '';
        $stdout      = $this->process->getOutput();
        $stderr      = $this->process->getErrorOutput();

        return 'This worker has crashed.' . PHP_EOL
            . $lastCommand . PHP_EOL
            . 'STDOUT:' . PHP_EOL
            . '----------------------' . PHP_EOL
            . $stdout . PHP_EOL
            . 'STDERR:' . PHP_EOL
            . '----------------------' . PHP_EOL
            . $stderr;
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

        $this->input->write(serialize($commandArguments) . "\n");

        $this->currentlyExecuting = $test;
        $test->setLastCommand($command);
        $this->commands[] = $command;
        ++$this->inExecution;
    }

    public function printFeedback(ResultPrinter $printer): ?Reader
    {
        if ($this->currentlyExecuting === null) {
            return null;
        }

        return $printer->printFeedback($this->currentlyExecuting);
    }

    public function reset(): void
    {
        $this->currentlyExecuting = null;
    }

    public function stop(): void
    {
        $this->input->write(self::COMMAND_EXIT);
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
        clearstatcache(true, $this->writeToPathname);

        return $this->inExecution === filesize($this->writeToPathname);
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }
}
