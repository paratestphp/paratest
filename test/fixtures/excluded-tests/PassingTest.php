<?php

declare(strict_types=1);

class PassingTest extends PHPUnit\Framework\TestCase
{
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }
}
