<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\depends_across_items;

use PHPUnit\Framework\Attributes\DependsExternal;
use PHPUnit\Framework\TestCase;

/** @internal */
final class EChainThirdTest extends TestCase
{
    #[DependsExternal(FChainSecondTest::class, 'testSecond')]
    public function testThird(string $value): void
    {
        self::assertSame('first, second', $value);
    }
}
