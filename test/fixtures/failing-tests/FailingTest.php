<?php

class FailingTest extends \PHPUnit\Framework\TestCase
{
    public function testInvalidLogic()
    {
        $this->assertFalse(true);
        $this->assertTrue(false);
    }
}
