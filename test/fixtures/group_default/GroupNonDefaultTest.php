<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\passing_tests;

use PHPUnit\Framework\TestCase;

/** @group group1 */
final class GroupNonDefaultTest extends TestCase
{
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }
}
