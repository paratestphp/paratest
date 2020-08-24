<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\skipped_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class SkippedAndIncompleteDataProviderTest extends TestCase
{
    /**
     * @return int[][]
     */
    public function dataProviderNumeric100(): array
    {
        $result = [];
        for ($i = 0; $i < 100; $i++) {
            $result[] = [$i, $i];
        }

        return $result;
    }

    /**
     * @dataProvider dataProviderNumeric100
     */
    public function testDataProviderWithSkipped(int $expected, int $actual): void
    {
        if ($expected % 3 === 0) {
            $this->markTestSkipped();
        } elseif ($expected % 3 === 1) {
            $this->markTestIncomplete();
        }

        $this->assertEquals($expected, $actual);
    }
}
