<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 7 tests: 3 x 2 from the providers, 1 plain.
 *
 * The FIRST method of this class uses a data provider, so children[0] of the
 * class-level TestSuite is a DataProviderTestSuite. Since DataProviderTestSuite
 * extends TestSuite, SuiteLoader::extractClassSuites() mistakes this class for a
 * wrapper suite, recurses into it, keeps only the three provider methods as shard
 * items and silently drops testPlain() from the sharded suite.
 *
 * Three provider methods are needed so that a shard boundary falls *inside* this
 * class: with 2 shards the items are
 *
 *     [Alpha::first, Alpha::second] [Alpha::third, Bravo]
 *
 * so AlphaTest.php is dispatched to both shards and runs twice. With only two
 * provider methods the boundary would fall exactly between AlphaTest and
 * BravoTest, and `sequential` would show the wrong testCount but no duplication.
 */
final class AlphaTest extends TestCase
{
    #[DataProvider('provider')]
    public function testFirstWithProvider(int $value): void
    {
        self::assertGreaterThan(0, $value);
    }

    #[DataProvider('provider')]
    public function testSecondWithProvider(int $value): void
    {
        self::assertGreaterThan(0, $value);
    }

    #[DataProvider('provider')]
    public function testThirdWithProvider(int $value): void
    {
        self::assertGreaterThan(0, $value);
    }

    public function testPlain(): void
    {
        self::assertTrue(true);
    }

    /** @return list<array{int}> */
    public static function provider(): array
    {
        return [[1], [2]];
    }
}
