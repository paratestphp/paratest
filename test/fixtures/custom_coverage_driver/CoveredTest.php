<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\custom_coverage_driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** @internal */
#[CoversClass(Covered::class)]
final class CoveredTest extends TestCase
{
    public function testFirst(): void
    {
        self::assertTrue(true);
    }

    public function testSecond(): void
    {
        self::assertTrue(true);
    }
}
