<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\common_results;

use PHPUnit\Framework\TestCase;

/** @internal */
final class SuccessTest extends TestCase
{
    public function testSuccess(): void
    {
        $this->assertTrue(true);
    }
}
