<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use ParaTest\Runners\PHPUnit\Options;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;

use function array_map;
use function count;
use function end;
use function explode;
use function fclose;
use function fread;
use function getenv;
use function implode;
use function is_numeric;
use function is_resource;
use function proc_get_status;
use function proc_open;
use function stream_get_contents;
use function stream_set_blocking;
use function strstr;

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
    /** @var array */
    protected $pipes;
    /** @var int */
    protected $inExecution = 0;
    /** @var int|null */
    private $exitCode = null;
    /** @var string */
    private $chunks = '';
    /** @var string */
    private $alreadyReadOutput = '';

    public function start(
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

        if ($uniqueToken) {
            $env['UNIQUE_TEST_TOKEN'] = $uniqueToken;
        }

        $finder        = new PhpExecutableFinder();
        $phpExecutable = $finder->find();
        $bin           = "$phpExecutable ";
        if ($options && $options->passthruPhp) {
            $bin .= implode(' ', $options->passthruPhp) . ' ';
        }

        $bin .= " \"$wrapperBinary\"";
        if ($parameters) {
            $bin .= ' ' . implode(' ', array_map('escapeshellarg', $parameters));
        }

        $pipes = [];
        if ($options && $options->verbose) {
            echo "Starting WrapperWorker via: $bin\n";
        }

        $process     = proc_open($bin, self::$descriptorspec, $pipes, null, $env);
        $this->proc  = is_resource($process) ? $process : null;
        $this->pipes = $pipes;
    }

    public function isFree(): bool
    {
        $this->checkNotCrashed();
        $this->updateStateFromAvailableOutput();

        return $this->inExecution === 0;
    }

    public function isRunning(): bool
    {
        if ($this->proc === null) {
            return false;
        }

        $status = proc_get_status($this->proc);

        return $status ? $status['running'] : false;
    }

    public function isStarted(): bool
    {
        return $this->proc !== null && $this->pipes !== null;
    }

    public function isCrashed(): bool
    {
        if (! $this->isStarted()) {
            return false;
        }

        $status = proc_get_status($this->proc);

        $this->updateStateFromAvailableOutput();

        $this->setExitCode($status);
        if ($this->exitCode === null) {
            return false;
        }

        return $this->exitCode !== 0;
    }

    public function checkNotCrashed(): void
    {
        if ($this->isCrashed()) {
            throw new RuntimeException($this->getCrashReport());
        }
    }

    public function getCrashReport(): string
    {
        $lastCommand = isset($this->commands) ? ' Last executed command: ' . end($this->commands) : '';

        return 'This worker has crashed.' . $lastCommand . PHP_EOL
            . 'Output:' . PHP_EOL
            . '----------------------' . PHP_EOL
            . $this->alreadyReadOutput . PHP_EOL
            . '----------------------' . PHP_EOL
            . $this->readAllStderr();
    }

    public function stop(): void
    {
        fclose($this->pipes[0]);
    }

    protected function setExitCode(array $status): void
    {
        if ($status['running']) {
            return;
        }

        if ($this->exitCode !== null) {
            return;
        }

        $this->exitCode = $status['exitcode'];
    }

    private function readAllStderr(): string
    {
        return stream_get_contents($this->pipes[2]);
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
            if (! strstr($line, "FINISHED\n")) {
                continue;
            }

            --$this->inExecution;
        }

        stream_set_blocking($this->pipes[1], true);
    }
}
