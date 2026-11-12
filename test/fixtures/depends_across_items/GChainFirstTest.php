<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\depends_across_items;

use PHPUnit\Framework\TestCase;

/** @internal */
final class GChainFirstTest extends TestCase
{
    public function testFirst(): string
    {
        self::assertTrue(true);

        return 'first';
    }
}
