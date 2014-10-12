<?php
class StopOnFailureTest extends PHPUnit_Framework_TestCase
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
