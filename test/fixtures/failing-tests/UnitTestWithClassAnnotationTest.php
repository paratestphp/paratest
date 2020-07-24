<?php

declare(strict_types=1);

namespace Fixtures\Tests;

use PHPUnit\Framework\TestCase;

use function sizeof;

/**
 * @runParallel
 * @pizzaBox
 */
class UnitTestWithClassAnnotationTest extends TestCase
{
    /**
     * @group fixtures
     * @pizza
     */
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }

    /**
     * @group fixtures
     * @pizza
     */
    public function testFalsehood(): void
    {
        $this->assertFalse(true);
    }

    /**
     * @group fixtures
     */
    public function testArrayLength(): void
    {
        $elems = [1, 2, 3, 4, 5];
        $this->assertEquals(5, sizeof($elems));
    }

    /**
     * @test
     */
    public function itsATest(): void
    {
        $this->assertTrue(true);
    }

    private function helperFunction(): void
    {
        echo 'I am super helpful';
    }
}
