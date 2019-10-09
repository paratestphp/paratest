<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Util;

use ParaTest\Util\Str;
use PHPUnit\Framework\TestCase;

class StrTest extends TestCase
{
    /**
     * @param string $delimiter
     * @param string $valueString
     * @param array  $expected
     * @dataProvider explodeWithCleanupDataProvider
     */
    public function testExplodeWithCleanup(string $delimiter, string $valueString, array $expected)
    {
        $actual = Str::explodeWithCleanup($delimiter, $valueString);
        $actual = array_values($actual);

        $this->assertEquals($expected, $actual);
    }

    public function explodeWithCleanupDataProvider()
    {
        return [
            'default' => [
                'delimiter' => ',',
                'valueString' => 'foo,bar',
                'expected' => [
                    'foo',
                    'bar',
                ],
            ],
            'other delimiter' => [
                'delimiter' => ';',
                'valueString' => 'foo;bar',
                'expected' => [
                    'foo',
                    'bar',
                ],
            ],
            'trims values' => [
                'delimiter' => ',',
                'valueString' => 'foo , bar',
                'expected' => [
                    'foo',
                    'bar',
                ],
            ],
            'empty' => [
                'delimiter' => ',',
                'valueString' => '',
                'expected' => [],
            ],
            'empty after trim' => [
                'delimiter' => ',',
                'valueString' => ' ',
                'expected' => [],
            ],
        ];
    }
}
