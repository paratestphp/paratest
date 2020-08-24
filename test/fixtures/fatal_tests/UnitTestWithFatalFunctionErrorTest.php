<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\fatal_tests;

use PHPUnit\Framework\TestCase;

/**
 * @runParallel
 *
 * @internal
 */
final class UnitTestWithFatalFunctionErrorTest extends TestCase
{
    /**
     * @group fixtures
     */
    public function testTruth(): void
    {
        $fatal = static function (): void {
            inexistent();
        };

        $fatal();
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
