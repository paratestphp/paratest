<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\deprecation_triggers\tests;

use ParaTest\Tests\fixtures\deprecation_triggers\src\FirstParty;
use PHPUnit\Framework\TestCase;

/** @internal */
final class SelfDeprecationTest extends TestCase
{
    public function testSelfDeprecation(): void
    {
        self::assertTrue(FirstParty::selfDeprecation());
    }
}
