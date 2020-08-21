<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\fatal_tests;

use PHPUnit\Framework\TestCase;

/**
 * @runParallel
 */
final class UnitTestWithFatalParseErrorTest extends TestCase
{
    /**
     * @group fixtures
     */
    public function testTruth(): void
    {
        I will fail fataly because this is not a php statement .
    }

    /**
     * @test
     */
    public function isItFalse(): void
    {
        sleep(2);
        $this->assertFalse(false);
    }
}
