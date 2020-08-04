<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

interface RunnerInterface
{
    public function run(): void;

    public function getExitCode(): int;
}
