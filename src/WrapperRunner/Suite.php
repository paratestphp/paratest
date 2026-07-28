<?php

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

/** @internal */
final readonly class Suite
{
    /** @param list<non-empty-string> $tests */
    public function __construct(
        private int   $testCount,
        private array $tests,
    ) {
    }

    public function getTestCount(): int
    {
        return $this->testCount;
    }

    /** @return list<non-empty-string> */
    public function getTests(): array
    {
        return $this->tests;
    }
}
