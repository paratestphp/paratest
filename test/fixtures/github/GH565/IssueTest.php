<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH565;

use LogicException;
use PHPUnit\Framework\TestCase;

/** @internal */
final class IssueTest extends TestCase
{
    /** @dataProvider provideIncomplete */
    public function testIncompleteByDataProvider(): void
    {
    }

    public static function provideIncomplete(): void
    {
        self::markTestIncomplete('foo');
    }

    /** @dataProvider provideSkipped */
    public function testSkippedByDataProvider(): void
    {
    }

    public static function provideSkipped(): void
    {
        self::markTestSkipped('bar');
    }

    /** @dataProvider provideError */
    public function testErrorByDataProvider(): void
    {
    }

    public static function provideError(): void
    {
        throw new LogicException('baz');
    }
}
