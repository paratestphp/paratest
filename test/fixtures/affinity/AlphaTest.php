<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\affinity;

use ParaTest\AffinityAware;
use PHPUnit\Framework\TestCase;

/** @internal */
final class AlphaTest extends TestCase implements AffinityAware
{
    public function getAffinity(): string|null
    {
        return 'A';
    }

    public function testOne(): void
    {
        self::assertTrue(true);
    }

    public function testTwo(): void
    {
        self::assertTrue(true);
    }
}
