<?php

namespace ParaTest\Tests\fixtures\system_out;

use PHPUnit\Framework\TestCase;

final class SystemOutTest extends TestCase
{
    public function testError()
    {
        echo'myError';
        foo();
    }

    public function testFailure()
    {
        echo 'myFailure';
        self::assertFalse(true);
    }

    public function testRisky()
    {
        echo 'myRisky';
    }

    public function testSuccess()
    {
        echo 'mySuccess';
        self::assertTrue(true);
    }
}