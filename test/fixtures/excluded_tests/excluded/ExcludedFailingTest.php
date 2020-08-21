<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\excluded_tests\excluded;

use PHPUnit\Framework\TestCase;

final class ExcludedFailingTest extends TestCase
{
    public function testFail(): void
    {
        $this->assertTrue(false);
    }
}
