<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\depends_across_items;

use PHPUnit\Framework\Attributes\DependsExternal;
use PHPUnit\Framework\TestCase;

/** @internal */
final class AConsumerTest extends TestCase
{
    #[DependsExternal(BProducerTest::class, 'testProduce')]
    public function testConsume(string $value): void
    {
        self::assertSame('b', $value);
    }
}
