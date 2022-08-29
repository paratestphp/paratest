<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\warning_tests;

use PHPUnit\Framework\TestCase;

use function uniqid;

/** @internal */
final class HasWarningsTest extends TestCase
{
    public function testPassingTest(): void
    {
        $this->assertTrue(true);
    }

    public function testForcedWarning(): void
    {
        $this->addWarning(uniqid('paratest_warning_'));
    }
}
