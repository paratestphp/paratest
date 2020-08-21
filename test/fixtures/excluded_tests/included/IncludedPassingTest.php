<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\excluded_tests\included;

use PHPUnit\Framework\TestCase;

final class IncludedPassingTest extends TestCase
{
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }
}
