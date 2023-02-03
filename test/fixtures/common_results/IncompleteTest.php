<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\common_results;

use PHPUnit\Framework\TestCase;

/** @internal */
final class IncompleteTest extends TestCase
{
    public function testIncomplete(): void
    {
        self::markTestIncomplete();
    }
}
