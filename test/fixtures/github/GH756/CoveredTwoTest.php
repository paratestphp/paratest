<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH756;

use PHPUnit\Framework\Attributes\IgnoreMethodForCodeCoverage;
use PHPUnit\Framework\TestCase;

/** @internal */
#[IgnoreMethodForCodeCoverage(CoveredTwoClass::class, 'n')]
final class CoveredTwoTest extends TestCase
{
    public function testOne(): void
    {
        $this->assertTrue((new CoveredTwoClass())->m());
    }
}
