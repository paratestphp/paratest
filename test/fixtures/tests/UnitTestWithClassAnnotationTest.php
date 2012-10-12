<?php
/**
 * @runParallel
 */
class UnitTestWithClassAnnotationTest extends PHPUnit_Framework_TestCase
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
        $elems = [1,2,3,4,5];
        $this->assertEquals(5, sizeof($elems));
    }
}

class UnitTestWithNoClassAnnotation extends PHPUnit_Framework_TestCase
{
    /**
     * @group fixtures
     */
    public function testString()
    {
        $this->assertEquals("hello world", $str);
    }
}