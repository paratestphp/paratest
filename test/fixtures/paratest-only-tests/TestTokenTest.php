<?php

class TestTokenTest extends PHPUnit\Framework\TestCase
{
    public function testThereIsAToken()
    {
        $token = getenv('TEST_TOKEN');
        $this->assertTrue(false !== $token);
    }
}
