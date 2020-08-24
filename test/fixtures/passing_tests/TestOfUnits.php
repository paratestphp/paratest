<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\passing_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TestOfUnits extends TestCase
{
    /**
     * @group fixtures
     */
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }

    /**
     * @group fixtures
     */
    public function testArrayLength(): void
    {
        $elems = [1, 2, 3, 4, 5];
        $this->assertCount(5, $elems);
    }
}
