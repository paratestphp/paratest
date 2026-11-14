<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\deprecation_triggers\tests;

use ParaTest\Tests\fixtures\deprecation_triggers\src\FirstParty;
use PHPUnit\Framework\TestCase;

/** @internal */
final class DirectDeprecationTest extends TestCase
{
    public function testDirectDeprecation(): void
    {
        self::assertTrue(FirstParty::directDeprecation());
    }
}
