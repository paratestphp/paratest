<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\system_out;

use PHPUnit\Framework\TestCase;

final class SystemOutTest extends TestCase
{
    public function testError(): void
    {
        echo 'myError';
        foo();
    }

    public function testFailure(): void
    {
        echo 'myFailure';
        self::assertFalse(true);
    }

    public function testRisky(): void
    {
        echo 'myRisky';
    }

    public function testSuccess(): void
    {
        echo 'mySuccess';
        self::assertTrue(true);
    }
}
