<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SkippedTest extends TestCase
{
    public function testSkipped(): void
    {
        $this->markTestSkipped();
    }
}
