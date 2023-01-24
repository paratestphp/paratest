<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use function array_map;
use function array_sum;

/**
 * A suite represents an entire PHPUnit Test Suite
 * object - this class is essentially used for running
 * entire test classes in parallel
 *
 * @internal
 */
final class Suite extends ExecutableTest
{
    public function __construct(
        private int $testCount,
        string      $path,
    )
    {
        parent::__construct($path);
    }

    /**
     * Get the expected count of tests to be executed.
     *
     * @return int
     */
    public function getTestCount(): int
    {
        return $this->testCount;
    }

    /** @inheritDoc */
    protected function prepareOptions(array $options): array
    {
        return $options;
    }
}
