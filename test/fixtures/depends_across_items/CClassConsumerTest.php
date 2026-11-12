<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\depends_across_items;

use PHPUnit\Framework\Attributes\DependsOnClass;
use PHPUnit\Framework\TestCase;

/** @internal */
final class CClassConsumerTest extends TestCase
{
    #[DependsOnClass(DClassProducerTest::class)]
    public function testConsume(): void
    {
        self::assertTrue(true);
    }
}
