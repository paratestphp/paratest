<?php

declare(strict_types=1);

namespace ParaTest\Tests;

/** @immutable */
final class RunnerResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output
    ) {
    }
}
