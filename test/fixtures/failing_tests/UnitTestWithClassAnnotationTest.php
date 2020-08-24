<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\failing_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @runParallel
 * @pizzaBox
 */
final class UnitTestWithClassAnnotationTest extends TestCase
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
        $this->assertCount(5, $elems);
    }

    /**
     * @test
     */
    public function itsATest(): void
    {
        $this->assertTrue(true);
    }

    // phpcs:ignore SlevomatCodingStandard.Classes.UnusedPrivateElements.UnusedMethod
    private function helperFunction(): void
    {
        echo 'I am super helpful';
    }
}
