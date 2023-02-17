<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\deterministic_random;

use PHPUnit\Framework\TestCase;

use function mt_rand;

/** @internal */
final class MtRandTest extends TestCase
{
    /**
     * Hardcoded on purpose, the seed is hardcoded too in:
     *
     * @see \ParaTest\Tests\Unit\Runners\PHPUnit\RunnerTestCase::testRandomnessIsDeterministic
     */
    public function testMtRandIsDeterministic(): void
    {
        self::assertSame(1495656191, mt_rand());
    }
}
