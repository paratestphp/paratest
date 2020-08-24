<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\failing_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class StopOnFailureTest extends TestCase
{
    /**
     * @group fixtures
     * @group slow
     */
    public function testOne(): void
    {
        $this->assertTrue(true);
    }

    /**
     * @group fixtures
     */
    public function testTwo(): void
    {
        $this->assertFalse(true);
    }

    /**
     * @group fixtures
     */
    public function testThree(): void
    {
        $this->assertTrue(true);
    }

    /**
     * @group fixtures
     */
    public function testFour(): void
    {
        $this->assertTrue(false);
    }
}
