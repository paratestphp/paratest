<?php

class TestTokenTest extends PHPUnit_FrameWork_TestCase
{
    public function testThereIsAToken()
    {
        $token = getenv("TEST_TOKEN");
        $this->assertTrue(false !== $token);
    }
}