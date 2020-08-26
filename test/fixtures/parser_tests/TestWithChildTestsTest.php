<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\parser_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TestWithChildTestsTest extends TestCase
{
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }
}
