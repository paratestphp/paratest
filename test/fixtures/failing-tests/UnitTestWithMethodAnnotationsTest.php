<?php
class UnitTestWithMethodAnnotationsTest extends PHPUnit_Framework_TestCase
{
    /**
     * @group fixtures
     */
    public function testTruth()
    {
        $this->assertTrue(true);
    }

    /**
     * @group fixtures
     */
    public function testFalsehood()
    {
        $this->assertFalse(true);
    }

    /**
     * @group fixtures
     */
    public function testArrayLength()
    {
        $elems = array(1,2,3,4,5);
        $this->assertEquals(5, sizeof($elems));
    }
}