<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\passthru_tests\level2;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @runParallel
 */

final class UnitTestInSubSubLevelTest extends TestCase
{
    /**
     * @group fixtures
     */
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }

    /**
     * @group fixtures
     */
    public function testFalsehood(): void
    {
        $this->assertFalse(false);
    }

    /**
     * @group fixtures
     */
    public function testArrayLength(): void
    {
        $elems = [1, 2, 3, 4, 5];
        $this->assertCount(5, $elems);
    }
}
