<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH782;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
final class IssueTest extends TestCase
{
    #[DataProvider('provideThings')]
    public function testProvider(bool $value): void
    {
        self::assertTrue((new Something($value))->value);
    }

    /** @return list<list<bool>> */
    public static function provideThings(): array
    {
        return [
            [true],
        ];
    }
}
