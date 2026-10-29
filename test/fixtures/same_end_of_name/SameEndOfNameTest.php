<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\same_end_of_name;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class SameEndOfNameTest extends TestCase
{
    /** @return list<array{bool}> */
    public static function dataProvider(): array
    {
        return [[true], [true]];
    }

    #[Test]
    public function create(): void
    {
        self::assertTrue(true);
    }

    #[Test]
    public function recreate(): void
    {
        self::assertTrue(true);
    }

    #[Test]
    #[DataProvider('dataProvider')]
    public function load(bool $value): void
    {
        self::assertTrue($value);
    }

    #[Test]
    #[DataProvider('dataProvider')]
    public function reload(bool $value): void
    {
        self::assertTrue($value);
    }
}
