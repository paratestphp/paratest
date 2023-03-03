<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\testdox_dataprovider;

use PHPUnit\Framework\TestCase;

final class TestdoxDataProviderTest extends TestCase
{
    /**
     * @dataProvider provideTrimData
     */
    public function testTrim(string $expectedResult, string $input): void
    {
        self::assertSame($expectedResult, trim($input));
    }

    public function provideTrimData(): array
    {
        return [
            'leading space is trimmed' => [
                'Hello World',
                ' Hello World',
            ],
            'trailing space and newline are trimmed' => [
                'Hello World',
                "Hello World \n",
            ],
        ];
    }
}