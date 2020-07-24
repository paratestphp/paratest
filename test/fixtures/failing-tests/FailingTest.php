<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class FailingTest extends TestCase
{
    public function testInvalidLogic(): void
    {
        $this->assertFalse(true);
        $this->assertTrue(false);
    }
}
