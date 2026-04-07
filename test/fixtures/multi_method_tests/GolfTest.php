<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\multi_method_tests;

use PHPUnit\Framework\TestCase;

/** @internal */
final class GolfTest extends TestCase
{
    public function testThirteen(): void
    {
        self::assertTrue(true);
    }

    public function testFourteen(): void
    {
        self::assertTrue(true);
    }
}
