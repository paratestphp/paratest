<?php

declare(strict_types=1);

namespace ParaTest\Tests;

/** @immutable */
final readonly class RunnerResult
{
    public function __construct(
        public int $exitCode,
        public string $output
    ) {
    }
}
