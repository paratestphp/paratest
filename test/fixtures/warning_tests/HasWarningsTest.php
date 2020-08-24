<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\warning_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class HasWarningsTest extends TestCase
{
    public function testPassingTest(): void
    {
        $this->assertTrue(true);
    }

    // phpcs:ignore SlevomatCodingStandard.Classes.UnusedPrivateElements.UnusedMethod
    private function testPrivateTest(): void
    {
        $this->assertTrue(true);
    }

    // phpcs:disable SlevomatCodingStandard.Classes.UnusedPrivateElements.UnusedMethod

    /**
     * @dataProvider llamas
     */
    private function testMissingDataProvider(): void
    {
        $this->assertTrue(true);
    }

    // phpcs:enable
}
