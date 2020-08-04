<?php

declare(strict_types=1);

final class PassingTest extends PHPUnit\Framework\TestCase
{
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }
}
