<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\order_by;

use PHPUnit\Framework\TestCase;

/** @internal */
final class BFailingTest extends TestCase
{
    public function testFailure(): void
    {
        $this->assertTrue(false);
    }
}
