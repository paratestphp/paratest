<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\parallel_suite\One;

use ParaTest\Tests\fixtures\parallel_suite\ParallelBase;

/** @internal */
final class OneTest extends ParallelBase
{
    /** @dataProvider provideDatas */
    public function testWithProvider(int $var): void
    {
        self::assertGreaterThan(0, $var);
    }

    /** @return array<string|int, non-empty-list<int>> */
    public static function provideDatas(): array
    {
        return [
            'one' => [1],
            [2],
            [3],
        ];
    }
}
