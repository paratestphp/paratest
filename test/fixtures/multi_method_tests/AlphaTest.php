<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\multi_method_tests;

use PHPUnit\Framework\TestCase;

/** @internal */
final class AlphaTest extends TestCase
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
}
