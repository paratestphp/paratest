<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\affinity;

use ParaTest\AffinityAware;
use PHPUnit\Framework\TestCase;

/** @internal */
final class NullTest extends TestCase implements AffinityAware
{
    public function getAffinity(): string|null
    {
        return null;
    }

    public function testSix(): void
    {
        self::assertTrue(true);
    }
}
