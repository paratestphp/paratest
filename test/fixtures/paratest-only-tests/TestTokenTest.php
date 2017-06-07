<?php

class TestTokenTest extends PHPUnit\FrameWork\TestCase
{
    public function testThereIsAToken()
    {
        $token = getenv("TEST_TOKEN");
        $this->assertTrue(false !== $token);
    }
}