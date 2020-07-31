<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FailureTest extends TestCase
{
    public function testFailure(): void
    {
        $this->assertTrue(false);
    }
}
