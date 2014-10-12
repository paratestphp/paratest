<?php

class HasWarningsTest extends PHPUnit_Framework_TestCase
{
    public function testPassingTest()
    {
        $this->assertTrue(true);
    }

    private function testPrivateTest()
    {
        $this->assertTrue(true);
    }

    /**
     * @dataProvider llamas
     */
    private function testMissingDataProvider()
    {
        $this->assertTrue(true);
    }
}
