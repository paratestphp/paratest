<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\depends_across_items;

use PHPUnit\Framework\Attributes\DependsExternal;
use PHPUnit\Framework\TestCase;

/** @internal */
final class FChainSecondTest extends TestCase
{
    #[DependsExternal(GChainFirstTest::class, 'testFirst')]
    public function testSecond(string $value): string
    {
        self::assertSame('first', $value);

        return $value . ', second';
    }
}
