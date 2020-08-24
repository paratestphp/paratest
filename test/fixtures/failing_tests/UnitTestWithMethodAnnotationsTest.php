<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\failing_tests;

use PHPUnit\Framework\TestCase;

use function uniqid;

/**
 * @internal
 */
class UnitTestWithMethodAnnotationsTest extends TestCase
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
    public function testFalsehood(): void
    {
        $this->assertFalse(true);
    }

    /**
     * @group fixtures
     */
    public function testArrayLength(): void
    {
        $elems = [1, 2, 3, 4, 5];
        $this->assertCount(5, $elems);
    }

    /**
     * @group fixtures
     */
    public function testWarning(): void
    {
        $this->addWarning(uniqid());
    }

    /**
     * @group fixtures
     */
    public function testSkipped(): void
    {
        $this->markTestSkipped();
    }

    /**
     * @group fixtures
     */
    public function testIncomplete(): void
    {
        $this->markTestIncomplete();
    }

    /**
     * @group fixtures
     */
    public function testRisky(): void
    {
        $this->markAsRisky();
    }
}
