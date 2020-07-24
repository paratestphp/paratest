<?php

declare(strict_types=1);

abstract class AbstractTest extends PHPUnit\Framework\TestCase
{
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }
}
