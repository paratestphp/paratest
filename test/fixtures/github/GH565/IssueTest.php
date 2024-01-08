<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH565;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
final class IssueTest extends TestCase
{
    #[DataProvider('provideIncomplete')]
    public function testIncompleteByDataProvider(): void
    {
    }

    public static function provideIncomplete(): void
    {
        self::markTestIncomplete('foo');
    }

    #[DataProvider('provideSkipped')]
    public function testSkippedByDataProvider(): void
    {
    }

    public static function provideSkipped(): void
    {
        self::markTestSkipped('bar');
    }

    #[DataProvider('provideError')]
    public function testErrorByDataProvider(): void
    {
    }

    public static function provideError(): void
    {
        throw new LogicException('baz');
    }
}
