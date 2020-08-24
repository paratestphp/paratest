<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\passing_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PreviouslyLoadedTest extends TestCase
{
    public function testRuns(): void
    {
        $this->assertTrue(true);
    }
}
