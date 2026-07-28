<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * 4 tests, no data provider at all.
 *
 * Control case: this class is classified correctly, so its shard always reports
 * 4 / 4 (100%).
 */
final class BravoTest extends TestCase
{
    public function testOne(): void
    {
        self::assertTrue(true);
    }

    public function testTwo(): void
    {
        self::assertTrue(true);
    }

    public function testThree(): void
    {
        self::assertTrue(true);
    }

    public function testFour(): void
    {
        self::assertTrue(true);
    }
}
