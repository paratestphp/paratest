<?php
/**
 * @runParallel
 */
class UnitTestInSubSubLevelTest extends PHPUnit_Framework_TestCase
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
        sleep(2);
        $this->assertFalse(false);
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