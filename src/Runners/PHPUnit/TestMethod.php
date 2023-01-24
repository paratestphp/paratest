<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use function array_reduce;
use function count;
use function implode;
use function preg_quote;
use function str_contains;

/**
 * Represents a set of tests grouped in batch which can be passed to a single phpunit process.
 * Batch limited to run tests only from one php test case file.
 * Used for running ParaTest in functional mode.
 *
 * @internal
 */
final class TestMethod
{
    /**
     * @paslm-param 0|positive-int $testCount
     */
    public function __construct(int $testCount)
    {
        $this->testCount = $testCount;
    }

    /**
     * Get the expected count of tests to be executed.
     *
     * @psalm-return 0|positive-int
     */
    public function getTestCount(): int
    {
        return $this->testCount;
    }
}
