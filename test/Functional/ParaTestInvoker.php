<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use Habitat\Habitat;
use Symfony\Component\Process\Process;

use function defined;
use function is_callable;
use function is_numeric;
use function strlen;

use const PHP_BINARY;

final class ParaTestInvoker
{
    /** @var string  */
    public $path;
    /** @var string  */
    public $bootstrap;

    public function __construct(string $path, string $bootstrap)
    {
        $this->path      = $path;
        $this->bootstrap = $bootstrap;
    }

    /**
     * Runs the command, returns the proc after it's done.
     *
     * @param array<int|string, string|int|null> $options
     *
     * @return Process<string>
     */
    public function execute(array $options = [], ?callable $callback = null): Process
    {
        $cmd  = $this->buildCommand($options);
        $env  = defined('PHP_WINDOWS_VERSION_BUILD') ? Habitat::getAll() : null;
        $proc = new Process($cmd, null, $env, null, $timeout = 60);

        if (! is_callable($callback)) {
            $proc->run();
        } else {
            $proc->run($callback);
        }

        return $proc;
    }

    /**
     * @param array<int|string, string|int|null> $options
     *
     * @return string[]
     */
    private function buildCommand(array $options = []): array
    {
        $cmd = [
            PHP_BINARY,
            PARA_BINARY,
            '--bootstrap',
            $this->bootstrap,
            '--phpunit',
            PHPUNIT,
        ];

        foreach ($options as $switch => $value) {
            if (is_numeric($switch)) {
                $switch = $value;
                $value  = null;
            }

            if (strlen($switch) > 1) {
                $switch = '--' . $switch;
            } else {
                $switch = '-' . $switch;
            }

            $cmd[] = $switch;
            if ($value === null) {
                continue;
            }

            $cmd[] = $value;
        }

        $cmd[] = $this->path;

        return $cmd;
    }
}
