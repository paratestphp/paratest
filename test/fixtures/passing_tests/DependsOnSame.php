<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\passing_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DependsOnSame extends TestCase
{
    public function testOneA(): string
    {
        $this->assertTrue(true);

        return 'twoA';
    }

    /**
     * @depends testOneA
     */
    public function testOneBDependsOnA(string $result): void
    {
        $this->assertEquals('twoA', $result);
    }

    /**
     * @depends testOneA
     */
    public function testOneCDependsOnA(string $result): void
    {
        $this->assertEquals('twoA', $result);
    }
}
