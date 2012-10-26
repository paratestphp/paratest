<?php
class LongRunningTest extends PHPUnit_Framework_TestCase
{
    /**
     * @group fixtures
     */
    public function testOne()
    {
        sleep(5);
        $this->assertTrue(true);
    }

    /**
     * @group fixtures
     */
    public function testTwo()
    {
        sleep(5);
        $this->assertFalse(true);
    }

    /**
     * @group fixtures
     */
    public function testThree()
    {
        sleep(5);
        $elems = array(1,2,3,4,5);
        $this->assertEquals(5, sizeof($elems));
    }
}