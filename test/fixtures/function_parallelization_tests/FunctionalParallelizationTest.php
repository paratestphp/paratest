<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\paratest_only_tests;

use PHPUnit\Framework\TestCase;

/** @internal */
final class FunctionalParallelizationTest extends TestCase
{
    /** @return list<array{string, string}> */
    public static function dataProvider1(): array
    {
        return [
            ['a', 'a'],
            ['b', 'b'],
            ['c', 'c'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function dataProvider2(): array
    {
        return [
            'test1 with spaces' => ['a', 'a'],
            "test2 with \0" => ['b', 'b'],
            'test3' => ['c', 'c'],
        ];
    }

    /** @dataProvider dataProvider1 */
    public function testDataProvider1(string $a, string $b): void
    {
        self::assertEquals($a, $b);
    }

    /** @dataProvider dataProvider2 */
    public function testDataProvider2(string $a, string $b): void
    {
        self::assertEquals($a, $b);
    }
}
