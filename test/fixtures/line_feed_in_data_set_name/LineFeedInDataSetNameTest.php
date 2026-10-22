<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\line_feed_in_data_set_name;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
final class LineFeedInDataSetNameTest extends TestCase
{
    /** @return array<string, array{bool}> */
    public static function dataProvider(): array
    {
        return [
            'line' => [true],
            "line\nfeed" => [true],
            "carriage\r\nreturn" => [true],
        ];
    }

    #[DataProvider('dataProvider')]
    public function testDataSetName(bool $value): void
    {
        self::assertTrue($value);
    }
}
