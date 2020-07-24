<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class FailureTest extends TestCase
{
    public function testFailure(): void
    {
        $this->assertTrue(false);
    }
}
