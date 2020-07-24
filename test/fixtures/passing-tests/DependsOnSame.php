<?php

declare(strict_types=1);

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
    public function testOneBDependsOnA($result): void
    {
        $this->assertEquals('twoA', $result);
    }

    /**
     * @depends testOneA
     */
    public function testOneCDependsOnA($result): void
    {
        $this->assertEquals('twoA', $result);
    }
}
