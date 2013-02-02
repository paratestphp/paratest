<?php
class BaseUnitTestCase extends PHPUnit_Framework_TestCase
{
    public function testShouldTakeAShortTimeByItself()
    {
        $this->shortTest();
    }

    private function shortTest()
    {
        for ($i = 0; $i <= 20000; $i++) {}
        $this->assertTrue(true);
    }
}
