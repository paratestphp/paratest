<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\WorkerCrashedException;
use PHPUnit\TextUI\TestRunner;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;

use function array_map;
use function array_merge;
use function assert;
use function count;
use function end;
use function escapeshellarg;
use function explode;
use function fread;
use function getenv;
use function implode;
use function is_resource;
use function proc_get_status;
use function proc_open;
use function sprintf;
use function stream_get_contents;
use function stream_set_blocking;
use function strstr;

use const DIRECTORY_SEPARATOR;
use const PHP_EOL;

abstract class BaseWorker
{
    /** @var resource|null */
    protected $proc;
    /** @var resource[] */
    protected $pipes = [];
    /** @var int */
    protected $inExecution = 0;
    /** @var OutputInterface */
    protected $output;
    /** @var string[] */
    protected $commands = [];
    /** @var bool */
    protected $running = false;
    /** @var string[][] */
    private static $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    /** @var int|null */
    private $exitCode = null;
    /** @var string */
    private $chunks = '';
    /** @var string */
    private $alreadyReadOutput = '';

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    final public function start(
        string $wrapperBinary,
        Options $options,
        int $token
    ): void {
        $env = array_merge(getenv(), $options->fillEnvWithTokens($token));

        $finder        = new PhpExecutableFinder();
        $phpExecutable = $finder->find();
        assert($phpExecutable !== false);

        $bin = escapeshellarg($phpExecutable);
        if (($passthruPhp = $options->passthruPhp()) !== null) {
            $bin .= ' ' . implode(' ', $passthruPhp) . ' ';
        }

        $bin .= ' ' . escapeshellarg($wrapperBinary);

        $parameters = [];
        $this->configureParameters($parameters);
        if (count($parameters) > 0) {
            $bin .= ' ' . implode(' ', array_map('escapeshellarg', $parameters));
        }

        $pipes = [];
        if ($options->verbose() > 0) {
            $this->output->writeln("Starting WrapperWorker via: {$bin}");
        }

        // Taken from \Symfony\Component\Process\Process::prepareWindowsCommandLine
        // Needed to handle spaces in the binary path, boring to test in CI
        if (DIRECTORY_SEPARATOR === '\\') {
            $bin = sprintf('cmd /V:ON /E:ON /D /C (%s)', $bin); // @codeCoverageIgnore
        }

        $process     = proc_open($bin, self::$descriptorspec, $pipes, null, $env);
        $this->proc  = is_resource($process) ? $process : null;
        $this->pipes = $pipes;
    }

    /**
     * @param string[] $parameters
     */
    abstract protected function configureParameters(array &$parameters): void;

    final public function isFree(): bool
    {
        $this->checkNotCrashed();
        $this->updateStateFromAvailableOutput();

        return $this->inExecution === 0;
    }

    final public function isRunning(): bool
    {
        if ($this->proc === null) {
            return false;
        }

        $this->updateProcStatus();

        return $this->running;
    }

    final public function checkNotCrashed(): void
    {
        $this->updateStateFromAvailableOutput();
        $this->updateProcStatus();

        if (
            $this->exitCode > 0
            && $this->exitCode !== TestRunner::FAILURE_EXIT
            && $this->exitCode !== TestRunner::EXCEPTION_EXIT
        ) {
            throw new WorkerCrashedException($this->getCrashReport());
        }
    }

    final public function getCrashReport(): string
    {
        $lastCommand = count($this->commands) !== 0 ? 'Last executed command: ' . end($this->commands) : '';

        return 'This worker has crashed.' . PHP_EOL
            . $lastCommand . PHP_EOL
            . 'Output:' . PHP_EOL
            . '----------------------' . PHP_EOL
            . $this->alreadyReadOutput . PHP_EOL
            . '----------------------' . PHP_EOL
            . $this->readAllStderr();
    }

    final protected function updateProcStatus(): void
    {
        assert($this->proc !== null);
        $status = proc_get_status($this->proc);

        if ($status === false) {
            return;
        }

        $this->running = $status['running'];

        // From PHP manual:
        // Only first call of proc_get_status function return real value, next calls return -1.
        if ($this->running || $this->exitCode !== null) {
            return;
        }

        $this->exitCode = $status['exitcode'];
    }

    final public function getExitCode(): int
    {
        assert($this->exitCode !== null);

        return $this->exitCode;
    }

    private function readAllStderr(): string
    {
        $data = stream_get_contents($this->pipes[2]);
        assert($data !== false);

        return $data;
    }

    /**
     * Have to read even incomplete lines to play nice with stream_select()
     * Otherwise it would continue to non-block because there are bytes to be read,
     * but fgets() won't pick them up.
     */
    private function updateStateFromAvailableOutput(): void
    {
        if (! isset($this->pipes[1])) {
            return;
        }

        stream_set_blocking($this->pipes[1], false);
        while ($chunk = fread($this->pipes[1], 4096)) {
            $this->chunks            .= $chunk;
            $this->alreadyReadOutput .= $chunk;
        }

        $lines = explode("\n", $this->chunks);
        // last element is not a complete line,
        // becomes part of a line completed later
        $this->chunks = $lines[count($lines) - 1];
        unset($lines[count($lines) - 1]);
        // delivering complete lines to this Worker
        foreach ($lines as $line) {
            $line .= "\n";
            if (strstr($line, "FINISHED\n") === false) {
                continue;
            }

            --$this->inExecution;
        }

        stream_set_blocking($this->pipes[1], true);
    }
}
