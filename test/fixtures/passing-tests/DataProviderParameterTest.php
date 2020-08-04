<?php

declare(strict_types=1);

class DataProviderParameterTest extends PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider dataProviderWithParameter
     */
    public function testWithDataProvider(): void
    {
    }

    public function dataProviderWithParameter($testName)
    {
        self::assertEquals($testName, 'testWithDataProvider');

        return [
            [
            ]
        ];
    }
}
