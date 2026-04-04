<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\multi_method_tests;

use PHPUnit\Framework\TestCase;

/** @internal */
final class EchoTest extends TestCase
{
    public function testNine(): void
    {
        self::assertTrue(true);
    }
}
