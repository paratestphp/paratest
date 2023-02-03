<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\common_results;

use PHPUnit\Framework\TestCase;

/** @internal */
final class FailureTest extends TestCase
{
    public function testFailure(): void
    {
        $this->assertTrue(false);
    }
}
