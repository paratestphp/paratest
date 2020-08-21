<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\skipped_tests;

use PHPUnit\Framework\TestCase;

final class SkippedTest extends TestCase
{
    public function testSkipped(): void
    {
        $this->markTestSkipped();
    }
}
