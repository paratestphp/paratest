<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\deprecation_triggers\tests;

use ParaTest\Tests\fixtures\deprecation_triggers\third_party\ThirdParty;
use PHPUnit\Framework\TestCase;

/** @internal */
final class IndirectDeprecationTest extends TestCase
{
    public function testIndirectDeprecation(): void
    {
        self::assertTrue(ThirdParty::indirectDeprecation());
    }
}
