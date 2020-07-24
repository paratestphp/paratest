<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SkippedOrIncompleteTest extends TestCase
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

    public function dataProviderNumeric100()
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
    public function testDataProviderWithSkipped($expected, $actual): void
    {
        if ($expected % 3 === 0) {
            $this->markTestSkipped();
        } elseif ($expected % 3 === 1) {
            $this->markTestIncomplete();
        }

        $this->assertEquals($expected, $actual);
    }
}
