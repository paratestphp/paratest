<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\process_isolation;

use PHPUnit\Framework\TestCase;

use function getenv;
use function putenv;

/** @internal */
final class FooTest extends TestCase
{
    public function test1(): void
    {
        putenv('PROC_ISOLATION=myuniqvalue');

        self::assertNotFalse(getenv('PROC_ISOLATION'));
    }

    public function test2(): void
    {
        self::assertFalse(getenv('PROC_ISOLATION'));
    }
}
