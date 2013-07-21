<?php

class DependsOnChain extends PHPUnit_Framework_TestCase
{

    public function testOneA()
    {
        $this->assertTrue(true);
        return 'oneA';
    }

    /**
     * @depends testOneA
     */
    public function testOneBDependsOnA($result)
    {
        $this->assertEquals('oneA', $result);
        return 'oneB';
    }

    /**
     * @depends testOneBDependsOnA
     */
    public function testOneCDependsOnB($result)
    {
        $this->assertEquals('oneB', $result);
    }

    public function testTwoA()
    {
        $this->assertTrue(true);
        return 'twoA';
    }

    /**
     * @depends testTwoA
     */
    public function testTwoBDependsOnA($result)
    {
        $this->assertEquals('twoA', $result);
        return 'twoB';
    }
}