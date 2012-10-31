<?php

class GroupsTest extends PHPUnit_FrameWork_TestCase
{
    /**
     * @group group1
     */
    public function testTruth()
    {
        $this->assertTrue(true);
    }

    /**
     * @group group1
     */
    public function testFalsehood()
    {
        $this->assertFalse(false);
    }

    /**
     * @group group2
     */
    public function testArrayLength()
    {
        $values = array(1, 3, 4, 7);
        $this->assertEquals(4, count($values));
    }

    /**
     * @group group2
     * @group group3
     */
    public function testStringLength()
    {
        $string = "hello";
        $this->assertEquals(5, strlen($string));
    }

    public function testAddition()
    {
        $vals = 1 + 1;
        $this->assertEquals(2, $vals);
    }
}