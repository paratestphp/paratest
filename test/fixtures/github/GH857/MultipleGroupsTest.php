<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH857;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/** @internal */
final class MultipleGroupsTest extends TestCase
{
    #[Group('one')]
    public function testOne(): void
    {
        self::assertTrue(true);
    }

    #[Group('two')]
    public function testTwo(): void
    {
        self::assertTrue(true);
    }

    #[Group('three')]
    public function testThree(): void
    {
        self::assertTrue(true);
    }
}
