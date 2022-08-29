<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\passing_tests;

use PHPUnit\Framework\TestCase;

final class GroupDefaultTest extends TestCase
{
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }

    /** @covers ParaTest\Tests\fixtures\passing_tests\GroupDefaultTest */
    public function testTruthWithCovers(): void
    {
        $this->assertTrue(true);
    }

    /** @group group1 */
    public function testFalsehood(): void
    {
        $this->assertFalse(false);
    }
}
