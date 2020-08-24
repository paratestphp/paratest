<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\passing_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class FunctionalModeEachTestCalledOnce extends TestCase
{
    public function testOne(): void
    {
        $this->assertTrue(true);
    }

    public function testOneIsNotAlone(): void
    {
        $this->assertNotEmpty('This test is to ensure that in functional mode tests are not executed multiple times #53');
    }
}
