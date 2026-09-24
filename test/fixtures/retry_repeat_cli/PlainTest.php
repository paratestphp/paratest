<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\retry_repeat_cli;

use PHPUnit\Framework\TestCase;

/** @internal */
final class PlainTest extends TestCase
{
    public function testOne(): void
    {
        self::assertTrue(true);
    }

    public function testTwo(): void
    {
        self::assertTrue(true);
    }
}
