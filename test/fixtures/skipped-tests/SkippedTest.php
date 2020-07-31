<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SkippedTest extends TestCase
{
    public function testSkipped(): void
    {
        $this->markTestSkipped();
    }
}
