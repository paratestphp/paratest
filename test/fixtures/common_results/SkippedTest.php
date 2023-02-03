<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\common_results;

use PHPUnit\Framework\TestCase;

/** @internal */
final class SkippedTest extends TestCase
{
    public function testSkipped(): void
    {
        self::markTestSkipped();
    }
}
