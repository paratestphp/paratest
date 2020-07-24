<?php

declare(strict_types=1);

class LongRunningTest extends PHPUnit\Framework\TestCase
{
    /**
     * @group fixtures
     */
    public function testOne(): void
    {
        sleep(5);
        $this->assertTrue(true);
    }

    /**
     * @group fixtures
     */
    public function testTwo(): void
    {
        sleep(5);
        $this->assertFalse(true);
    }

    /**
     * @group fixtures
     */
    public function testThree(): void
    {
        sleep(5);
        $elems = [1, 2, 3, 4, 5];
        $this->assertEquals(5, sizeof($elems));
    }
}
