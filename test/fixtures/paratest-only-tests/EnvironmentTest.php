<?php

declare(strict_types=1);

class EnvironmentTest extends PHPUnit\Framework\TestCase
{
    /**
     * @group fixtures
     */
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
