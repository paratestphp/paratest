<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\paratest_only_tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function getenv;
use function is_numeric;

/** @internal */
final class EnvironmentTest extends TestCase
{
    #[Group('fixtures')]
    public function testParatestVariableIsDefined(): void
    {
        $this->assertEquals(1, getenv('PARATEST'));
    }

    public function testTestTokenVariableIsDefinedCorrectly(): void
    {
        $token       = getenv('TEST_TOKEN');
        $unqiueToken = getenv('UNIQUE_TEST_TOKEN');
        $this->assertTrue(is_numeric($token));
        $this->assertTrue(! empty($unqiueToken));
    }
}
