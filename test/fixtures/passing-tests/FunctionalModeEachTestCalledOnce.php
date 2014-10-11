<?php

class FunctionalModeEachTestCalledOnce extends PHPUnit_Framework_TestCase
{
    public function testOne()
    {
        $this->assertTrue(true);
    }

    public function testOneIsNotAlone()
    {
        $this->assertNotEmpty('This test is to ensure that in functional mode tests are not executed multiple times #53');
    }
}