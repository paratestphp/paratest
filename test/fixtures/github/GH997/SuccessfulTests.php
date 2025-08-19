<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH997;

use PHPUnit\Framework\TestCase;

/** @internal */
final class SuccessfulTests extends TestCase
{
    public function testSuccessOne(): void
    {
        $this->assertTrue(true);
    }

    public function testSuccessTwo(): void
    {
        $this->assertTrue(true);
    }
}
