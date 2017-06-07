<?php

abstract class AbstractTest extends PHPUnit\Framework\TestCase
{
    public function testTruth()
    {
        $this->assertTrue(true);
    }
}