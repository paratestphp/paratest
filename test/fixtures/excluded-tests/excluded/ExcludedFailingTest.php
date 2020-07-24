<?php

declare(strict_types=1);

class ExcludedFailingTest extends PHPUnit\Framework\TestCase
{
    public function testFail(): void
    {
        $this->assertTrue(false);
    }
}
