<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\failing_tests;

use PHPUnit\Framework\TestCase;

use function trigger_error;

use const E_USER_WARNING;

/** @internal */
class UnitTestWithMethodAnnotationsTest extends TestCase
{
    /** @group fixtures */
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }

    /** @group fixtures */
    public function testFalsehood(): void
    {
        $this->assertSame('foo', 'bar');
    }

    /** @group fixtures */
    public function testArrayLength(): void
    {
        $elems = [1, 2, 3, 4, 5];
        $this->assertCount(5, $elems);
    }

    /** @group fixtures */
    public function testWarning(): void
    {
        trigger_error('MyWarning', E_USER_WARNING);
        self::assertCount(1, [1]);
    }

    /** @group fixtures */
    public function testSkipped(): void
    {
        $this->markTestSkipped();
    }

    /** @group fixtures */
    public function testIncomplete(): void
    {
        $this->markTestIncomplete();
    }

    /** @group fixtures */
    public function testRisky(): void
    {
    }
}
