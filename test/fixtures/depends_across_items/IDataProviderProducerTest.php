<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\depends_across_items;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
final class IDataProviderProducerTest extends TestCase
{
    /** @return iterable<string, array{bool}> */
    public static function provideValues(): iterable
    {
        yield 'first' => [true];
        yield 'second' => [true];
    }

    #[DataProvider('provideValues')]
    public function testProduce(bool $value): void
    {
        self::assertTrue($value);
    }
}
