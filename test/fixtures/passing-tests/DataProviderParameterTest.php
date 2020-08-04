<?php

declare(strict_types=1);

final class DataProviderParameterTest extends PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider dataProviderWithParameter
     */
    public function testWithDataProvider($expected): void
    {
        $this->assertTrue($expected);
    }

    public function dataProviderWithParameter($testName)
    {
        $this->assertEquals($testName, 'testWithDataProvider');

        return [
            [
                'expected' => true
            ]
        ];
    }
}
