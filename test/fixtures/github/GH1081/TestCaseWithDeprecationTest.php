<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH1081;

use PHPUnit\Framework\TestCase;

/** @internal */
final class TestCaseWithDeprecationTest extends TestCase
{
    public function testWithDeprecation(): void
    {
        self::assertTrue((new ClassWithDeprecation())->foo());
    }
}
