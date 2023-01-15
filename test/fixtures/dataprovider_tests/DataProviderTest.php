<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\dataprovider_tests;

use PHPUnit\Framework\TestCase;

/** @internal */
final class DataProviderTest extends TestCase
{
    /** @dataProvider dataProviderNumeric50 */
    public function testNumericDataProvider50(int $expected, int $actual): void
    {
        $this->assertEquals($expected, $actual);
    }

    /** @return int[][] */
    public static function dataProviderNumeric50(): array
    {
        $result = [];
        for ($i = 0; $i < 50; $i++) {
            $result[] = [$i, $i];
        }

        return $result;
    }

    /** @dataProvider dataProviderNamed50 */
    public function testNamedDataProvider50(int $expected, int $actual): void
    {
        $this->assertEquals($expected, $actual);
    }

    /** @return int[][] */
    public static function dataProviderNamed50(): array
    {
        $result = [];
        for ($i = 0; $i < 50; $i++) {
            $name          = 'name_of_test_' . $i;
            $result[$name] = [$i, $i];
        }

        return $result;
    }

    /** @dataProvider dataProviderNumeric1000 */
    public function testNumericDataProvider1000(int $expected, int $actual): void
    {
        $this->assertEquals($expected, $actual);
    }

    /** @return int[][] */
    public static function dataProviderNumeric1000(): array
    {
        $result = [];
        for ($i = 0; $i < 1000; $i++) {
            $result[] = [$i, $i];
        }

        return $result;
    }

    /** @dataProvider dataProviderIterable */
    public function testIterableDataProvider(int $expected, int $actual): void
    {
        $this->assertEquals($expected, $actual);
    }

    /** @return int[][] */
    public static function dataProviderIterable(): iterable
    {
        yield from self::dataProviderNumeric50();
    }
}
