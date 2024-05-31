<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH853;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_fill;

/** @internal */
final class ThousandTest extends TestCase
{
    /** @return list<positive-int> */
    public static function rangeOfNumbersProvider(): array
    {
        return array_fill(1, 1000, [1]);
    }

    #[DataProvider('rangeOfNumbersProvider')]
    public function testRangeOfNumbers(int $integer): void
    {
        $this->assertIsInt($integer);
    }
}
