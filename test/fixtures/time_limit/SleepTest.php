<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\time_limit;

use PHPUnit\Framework\TestCase;

use function sleep;

/** @internal */
final class SleepTest extends TestCase
{
    public function testsleep(): void
    {
        sleep(2);

        $this->assertTrue(true);
    }
}
