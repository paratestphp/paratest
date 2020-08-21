<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\warning_tests;

use PHPUnit\Framework\TestCase;

abstract class CustomAbstractTest extends TestCase
{
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }
}
