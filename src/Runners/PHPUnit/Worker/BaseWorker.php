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
use function getenv;
use function implode;
use function is_resource;
use function proc_get_status;
use function proc_open;
use function sprintf;
use function stream_get_contents;

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
    protected $alreadyReadOutput = '';

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
        if ($options->stopOnFailure()) {
            $parameters[] = '--stop-on-failure';
        }

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

        $process     = proc_open($bin, self::$descriptorspec, $pipes, $options->cwd(), $env);
        $this->proc  = is_resource($process) ? $process : null;
        $this->pipes = $pipes;
    }

    /**
     * @param string[] $parameters
     */
    abstract protected function configureParameters(array &$parameters): void;

    abstract public function isRunning(): bool;

    final protected function checkNotCrashed(): void
    {
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
        $lastCommand              = count($this->commands) !== 0 ? 'Last executed command: ' . end($this->commands) : '';
        $this->alreadyReadOutput .= (string) stream_get_contents($this->pipes[1]);

        return 'This worker has crashed.' . PHP_EOL
            . $lastCommand . PHP_EOL
            . 'STDOUT:' . PHP_EOL
            . '----------------------' . PHP_EOL
            . $this->alreadyReadOutput . PHP_EOL
            . 'STDERR:' . PHP_EOL
            . '----------------------' . PHP_EOL
            . $this->readAllStderr();
    }

    private function readAllStderr(): string
    {
        $data = stream_get_contents($this->pipes[2]);
        assert($data !== false);

        return $data;
    }

    final protected function updateProcStatus(): void
    {
        assert($this->proc !== null);
        $status = proc_get_status($this->proc);
        assert($status !== false);

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
}
