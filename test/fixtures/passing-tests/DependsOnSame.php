<?php

class DependsOnSame extends PHPUnit\Framework\TestCase
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