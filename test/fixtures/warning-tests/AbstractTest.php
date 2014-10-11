<?php

abstract class AbstractTest extends PHPUnit_Framework_TestCase
{
    public function testTruth()
    {
        $this->assertTrue(true);
    }
}