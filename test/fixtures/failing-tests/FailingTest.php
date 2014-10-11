<?php

class FailingTest extends \PHPUnit_Framework_TestCase
{
    public function testInvalidLogic()
    {
        $this->assertFalse(true);
        $this->assertTrue(false);
    }
}
