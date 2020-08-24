<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\passing_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DependsOnChain extends TestCase
{
    public function testOneA(): string
    {
        $this->assertTrue(true);

        return 'oneA';
    }

    /**
     * @depends testOneA
     */
    public function testOneBDependsOnA(string $result): string
    {
        $this->assertEquals('oneA', $result);

        return 'oneB';
    }

    /**
     * @depends testOneBDependsOnA
     */
    public function testOneCDependsOnB(string $result): void
    {
        $this->assertEquals('oneB', $result);
    }

    public function testTwoA(): string
    {
        $this->assertTrue(true);

        return 'twoA';
    }

    /**
     * @depends testTwoA
     */
    public function testTwoBDependsOnA(string $result): string
    {
        $this->assertEquals('twoA', $result);

        return 'twoB';
    }
}
