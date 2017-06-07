<?php
class StopOnFailureTest extends PHPUnit\Framework\TestCase
{
    /**
     * @group fixtures
     * @group slow
     */
    public function testOne()
    {
        $this->assertTrue(true);
    }

    /**
     * @group fixtures
     */
    public function testTwo()
    {
        $this->assertFalse(true);
    }

    /**
     * @group fixtures
     */
    public function testThree()
    {
        $this->assertTrue(true);
    }

    /**
     * @group fixtures
     */
    public function testFour()
    {
        $this->assertTrue(false);
    }
}
