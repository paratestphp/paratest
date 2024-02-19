<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\same_beginning_of_name;

use PHPUnit\Framework\TestCase;

/** @internal */
final class SameBeginningOfNameTest extends TestCase
{
    public function testSame(): void
    {
        self::assertTrue(true);
    }

    public function testSameBeginning(): void
    {
        self::assertTrue(true);
    }

    public function testSameBeginningOf(): void
    {
        self::assertTrue(true);
    }

    public function testSameBeginningOfName(): void
    {
        self::assertTrue(true);
    }
}
