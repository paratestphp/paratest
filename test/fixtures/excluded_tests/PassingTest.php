<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\excluded_tests;

use PHPUnit\Framework\TestCase;

final class PassingTest extends TestCase
{
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }
}
