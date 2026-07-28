<?php

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

use function array_merge;
use function array_values;

/** @internal */
final readonly class Suite
{
    /** @param array<list<non-empty-string>> $testsPerAffinity */
    public function __construct(
        private int $testCount,
        private array $testsPerAffinity,
    ) {
    }

    public function getTestCount(): int
    {
        return $this->testCount;
    }

    /** @return array<list<non-empty-string>> */
    public function getTestsPerAffinity(): array
    {
        return $this->testsPerAffinity;
    }

    /** @return list<non-empty-string> */
    public function getTests(): array
    {
        return array_merge(...array_values($this->testsPerAffinity));
    }
}
