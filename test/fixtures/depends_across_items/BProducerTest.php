<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\depends_across_items;

use PHPUnit\Framework\TestCase;

/** @internal */
final class BProducerTest extends TestCase
{
    public function testProduce(): string
    {
        self::assertTrue(true);

        return 'b';
    }
}
