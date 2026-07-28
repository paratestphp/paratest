<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\affinity;

use ParaTest\AffinityAware;
use PHPUnit\Framework\TestCase;

/** @internal */
final class BravoTest extends TestCase implements AffinityAware
{
    public function getAffinity(): string|null
    {
        return 'B';
    }

    public function testFour(): void
    {
        self::assertTrue(true);
    }
}
