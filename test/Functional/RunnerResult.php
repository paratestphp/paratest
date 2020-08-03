<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use ParaTest\Runners\PHPUnit\BaseRunner;
use Symfony\Component\Console\Output\BufferedOutput;

final class RunnerResult
{
    /** @var BaseRunner */
    private $runner;
    /** @var BufferedOutput */
    private $output;
    /** @var string */
    private $completeOutput = '';

    public function __construct(BaseRunner $runner, BufferedOutput $output)
    {
        $this->runner = $runner;
        $this->output = $output;
    }

    public function getOutput(): string
    {
        $this->completeOutput .= $this->output->fetch();

        return $this->completeOutput;
    }

    public function getExitCode(): int
    {
        return $this->runner->getExitCode();
    }
}
