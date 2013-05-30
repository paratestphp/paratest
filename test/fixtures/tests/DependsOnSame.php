<?php

class DependsOnSame extends PHPUnit_Framework_TestCase
{
    public function testOneA()
    {
        $this->assertTrue(true);
        return 'twoA';
    }

    /**
     * @depends testOneA
     */
    public function testOneBDependsOnA($result)
    {
        $this->assertEquals('twoA', $result);
    }

    /**
     * @depends testOneA
     */
    public function testOneCDependsOnA($result)
    {
        $this->assertEquals('twoA', $result);
    }
}