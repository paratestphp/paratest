<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\multi_method_tests;

use PHPUnit\Framework\TestCase;

/** @internal */
final class BravoTest extends TestCase
{
    public function testFour(): void
    {
        self::assertTrue(true);
    }

    public function testFive(): void
    {
        self::assertTrue(true);
    }
}
