<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\depends_across_items;

use PHPUnit\Framework\TestCase;

/** @internal */
final class DClassProducerTest extends TestCase
{
    public function testOne(): void
    {
        self::assertTrue(true);
    }

    public function testTwo(): void
    {
        self::assertTrue(true);
    }
}
