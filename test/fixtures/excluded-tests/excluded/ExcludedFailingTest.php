<?php

class ExcludedFailingTest extends PHPUnit_FrameWork_TestCase
{
    public function testFail()
    {
        $this->assertTrue(false);
    }
}