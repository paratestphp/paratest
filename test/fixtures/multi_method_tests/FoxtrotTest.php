<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\multi_method_tests;

use PHPUnit\Framework\TestCase;

/** @internal */
final class FoxtrotTest extends TestCase
{
    public function testTen(): void
    {
        self::assertTrue(true);
    }

    public function testEleven(): void
    {
        self::assertTrue(true);
    }

    public function testTwelve(): void
    {
        self::assertTrue(true);
    }
}
