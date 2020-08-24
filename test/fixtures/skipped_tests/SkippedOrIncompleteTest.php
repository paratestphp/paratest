<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\skipped_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class SkippedOrIncompleteTest extends TestCase
{
    /**
     * @group skipped-group
     */
    public function testSkipped(): void
    {
        $this->markTestSkipped();
    }

    /**
     * @group incomplete-group
     */
    public function testIncomplete(): void
    {
        $this->markTestIncomplete();
    }

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
     * @group dataset-group
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
