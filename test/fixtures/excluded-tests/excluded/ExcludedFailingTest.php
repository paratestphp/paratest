<?php

class ExcludedFailingTest extends PHPUnit\FrameWork\TestCase
{
    public function testFail()
    {
        $this->assertTrue(false);
    }
}