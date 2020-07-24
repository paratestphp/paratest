<?php

declare(strict_types=1);

class StopOnFailureTest extends PHPUnit\Framework\TestCase
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
