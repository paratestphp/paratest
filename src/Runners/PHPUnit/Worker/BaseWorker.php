<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use ParaTest\Runners\PHPUnit\Options;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;

use function array_map;
use function assert;
use function count;
use function end;
use function escapeshellarg;
use function explode;
use function fclose;
use function fread;
use function getenv;
use function implode;
use function is_numeric;
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
    /** @var string[][] */
    protected static $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
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

    /**
     * @param string[] $parameters
     */
    final public function start(
        string $wrapperBinary,
        ?int $token = 1,
        ?string $uniqueToken = null,
        array $parameters = [],
        ?Options $options = null
    ): void {
        $env             = getenv();
        $env['PARATEST'] = 1;
        if (is_numeric($token)) {
            $env['XDEBUG_CONFIG'] = 'true';
            $env['TEST_TOKEN']    = $token;
        }

        if ($uniqueToken !== null) {
            $env['UNIQUE_TEST_TOKEN'] = $uniqueToken;
        }

        $finder        = new PhpExecutableFinder();
        $phpExecutable = $finder->find();
        assert($phpExecutable !== false);

        $bin = escapeshellarg($phpExecutable);
        if ($options !== null && ($passthruPhp = $options->passthruPhp()) !== null) {
                $bin .= ' ' . implode(' ', $passthruPhp) . ' ';
        }

        $bin .= ' ' . escapeshellarg($wrapperBinary);

        $this->configureParameters($parameters);
        if (count($parameters) > 0) {
            $bin .= ' ' . implode(' ', array_map('escapeshellarg', $parameters));
        }

        $pipes = [];
        if ($options !== null && $options->verbose() > 0) {
            $this->output->writeln("Starting WrapperWorker via: $bin");
        }

        // Taken from \Symfony\Component\Process\Process::prepareWindowsCommandLine
        // Needed to handle spaces in the binary path, boring to test in CI
        if (DIRECTORY_SEPARATOR === '\\') {
            $bin = sprintf('cmd /V:ON /E:ON /D /C (%s)', $bin);
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

        $status = proc_get_status($this->proc);

        return $status !== false ? $status['running'] : false;
    }

    final public function isStarted(): bool
    {
        return $this->proc !== null && $this->pipes !== [];
    }

    final public function isCrashed(): bool
    {
        if (! $this->isStarted()) {
            return false;
        }

        assert($this->proc !== null);
        $status = proc_get_status($this->proc);
        assert($status !== false);

        $this->updateStateFromAvailableOutput();

        $this->setExitCode($status['running'], $status['exitcode']);
        if ($this->exitCode === null) {
            return false;
        }

        return $this->exitCode !== 0;
    }

    final public function checkNotCrashed(): void
    {
        if ($this->isCrashed()) {
            throw new RuntimeException($this->getCrashReport());
        }
    }

    final public function getCrashReport(): string
    {
        $lastCommand = count($this->commands) !== 0 ? ' Last executed command: ' . end($this->commands) : '';

        return 'This worker has crashed.' . $lastCommand . PHP_EOL
            . 'Output:' . PHP_EOL
            . '----------------------' . PHP_EOL
            . $this->alreadyReadOutput . PHP_EOL
            . '----------------------' . PHP_EOL
            . $this->readAllStderr();
    }

    final public function stop(): void
    {
        $this->doStop();
        fclose($this->pipes[0]);
    }

    abstract protected function doStop(): void;

    final protected function setExitCode(bool $running, int $exitcode): void
    {
        if ($running) {
            return;
        }

        if ($this->exitCode !== null) {
            return;
        }

        $this->exitCode = $exitcode;
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
