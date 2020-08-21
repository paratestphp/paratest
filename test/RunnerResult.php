<?php

declare(strict_types=1);

namespace ParaTest\Tests;

final class RunnerResult
{
    /** @var int */
    private $exitCode;
    /** @var string */
    private $output;

    public function __construct(int $exitCode, string $output)
    {
        $this->exitCode = $exitCode;
        $this->output   = $output;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }
}
