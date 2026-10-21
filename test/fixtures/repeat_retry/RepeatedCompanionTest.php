<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\repeat_retry;

use PHPUnit\Framework\Attributes\Repeat;
use PHPUnit\Framework\TestCase;

/** @internal */
final class RepeatedCompanionTest extends TestCase
{
    #[Repeat(2)]
    public function testCompanion(): void
    {
        self::assertTrue(true);
    }
}
