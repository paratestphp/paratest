<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\testdox_dataprovider;

use PHPUnit\Framework\TestCase;

use function trim;

final class TestdoxDataProviderTest extends TestCase
{
    /** @dataProvider provideTrimData */
    public function testTrim(string $expectedResult, string $input): void
    {
        self::assertSame($expectedResult, trim($input));
    }

    /** @return array<non-empty-string, list<non-empty-string>> */
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
