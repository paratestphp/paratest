<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\repeat_retry;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Repeat;
use PHPUnit\Framework\Attributes\Retry;
use PHPUnit\Framework\TestCase;

/**
 * Keep testRepeated first: class-level sharding inspects the first test of each class.
 *
 * @internal
 */
final class RepeatRetryAttributesTest extends TestCase
{
    #[Repeat(3)]
    public function testRepeated(): void
    {
        self::assertTrue(true);
    }

    public function testPlain(): void
    {
        self::assertTrue(true);
    }

    #[Retry(2)]
    public function testRetried(): void
    {
        self::assertTrue(true);
    }

    /** @return iterable<string, array{int}> */
    public static function provideData(): iterable
    {
        yield 'one' => [1];
        yield 'two' => [2];
    }

    #[DataProvider('provideData')]
    #[Repeat(2)]
    public function testRepeatedWithData(int $value): void
    {
        self::assertGreaterThan(0, $value);
    }
}
