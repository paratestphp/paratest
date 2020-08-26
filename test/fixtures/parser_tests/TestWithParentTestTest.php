<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\parser_tests;

/**
 * @internal
 */
final class TestWithParentTestTest extends TestWithChildTestsTest
{
    /**
     * @test
     */
    public function isItFalse(): void
    {
        $this->assertFalse(false);
    }
}
