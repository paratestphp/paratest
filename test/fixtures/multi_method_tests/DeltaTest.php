<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\multi_method_tests;

use PHPUnit\Framework\TestCase;

/** @internal */
final class DeltaTest extends TestCase
{
    public function testSeven(): void
    {
        self::assertTrue(true);
    }

    public function testEight(): void
    {
        self::assertTrue(true);
    }
}
