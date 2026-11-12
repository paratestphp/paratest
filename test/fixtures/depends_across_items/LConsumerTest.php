<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\depends_across_items;

use PHPUnit\Framework\Attributes\DependsExternal;
use PHPUnit\Framework\TestCase;

/** @internal */
final class LConsumerTest extends TestCase
{
    #[DependsExternal(KProducerTest::class, 'testProduce')]
    public function testConsume(string $value): void
    {
        self::assertSame('k', $value);
    }
}
