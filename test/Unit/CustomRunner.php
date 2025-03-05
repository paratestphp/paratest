<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit;

use ParaTest\RunnerInterface;

/** @internal */
final readonly class CustomRunner implements RunnerInterface
{
    public const EXIT_CODE = 99;

    public function run(): int
    {
        return self::EXIT_CODE;
    }
}
