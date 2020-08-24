<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\failing_tests;

use RuntimeException;

/**
 * @internal
 *
 * @runParallel
 */
final class UnitTestWithErrorTest extends UnitTestWithMethodAnnotationsTest
{
    /**
     * @group fixtures
     */
    public function testTruth(): void
    {
        throw new RuntimeException('Error!!!');
    }

    /**
     * @test
     */
    public function isItFalse(): void
    {
        $this->assertFalse(false);
    }
}
