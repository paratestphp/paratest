<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\failing_tests;

use PHPUnit\Framework\TestCase;

final class FailingTest extends TestCase
{
    public function testInvalidLogic(): void
    {
        $this->assertFalse(true);
        $this->assertTrue(false);
    }
}
