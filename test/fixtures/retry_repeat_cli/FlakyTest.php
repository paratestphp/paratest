<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\retry_repeat_cli;

use PHPUnit\Framework\TestCase;

/**
 * Fails on its first attempt and passes on any later attempt in the same process.
 *
 * @internal
 */
final class FlakyTest extends TestCase
{
    private static int $attempts = 0;

    public function testFailsOnFirstAttempt(): void
    {
        ++self::$attempts;

        self::assertGreaterThan(1, self::$attempts);
    }
}
