<?php

declare(strict_types=1);

namespace ParaTest;

interface RunnerInterface
{
    public const int SUCCESS_EXIT   = 0;
    public const int FAILURE_EXIT   = 1;
    public const int EXCEPTION_EXIT = 2;

    public function run(): int;
}
