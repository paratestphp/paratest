<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\parallel_suite\One;

use ParaTest\Tests\fixtures\parallel_suite\ParallelBase;

/**
 * @internal
 */
final class OneTest extends ParallelBase
{
    /**
     * @param int $var
     * @dataProvider provideDatas
     */
    public function testWithProvider($var)
    {
        self::assertGreaterThan(0, $var);
    }

    public function provideDatas(): array
    {
        return [
            'one' => [1],
            [2],
        ];
    }
}
