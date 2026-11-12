<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\depends_across_items;

use PHPUnit\Framework\Attributes\DependsExternal;
use PHPUnit\Framework\TestCase;

/** @internal */
final class HDataProviderConsumerTest extends TestCase
{
    #[DependsExternal(IDataProviderProducerTest::class, 'testProduce')]
    public function testConsume(): void
    {
        self::assertTrue(true);
    }
}
