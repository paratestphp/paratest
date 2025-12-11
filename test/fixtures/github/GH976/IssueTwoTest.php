<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH976;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** @internal */
#[CoversClass(SomethingTwo::class)]
final class IssueTwoTest extends TestCase
{
    public function testOpenClover(): void
    {
        self::assertTrue((new SomethingTwo())->easy());
    }
}
