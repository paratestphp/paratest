<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\parallel_suite\One;

use ParaTest\Tests\fixtures\parallel_suite\ParallelBase;

/** @internal */
final class FourTest extends ParallelBase
{
    public function testSub(): void
    {
        self::assertTrue(true);
    }
}
