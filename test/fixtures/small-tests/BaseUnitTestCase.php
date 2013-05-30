<?php
class BaseUnitTestCase extends PHPUnit_Framework_TestCase
{
    public function testShouldTakeAShortTimeByItself1()
    {
        $this->shortTest();
    }

    public function testShouldTakeAShortTimeByItself2()
    {
        $this->shortTest();
    }

    public function testShouldTakeAShortTimeByItself3()
    {
        $this->shortTest();
    }

    public function testShouldTakeAShortTimeByItself4()
    {
        $this->shortTest();
    }

    public function testShouldTakeAShortTimeByItself5()
    {
        $this->shortTest();
    }

    private function shortTest()
    {
        for ($i = 0; $i <= 20000; $i++) {}
        $this->assertTrue(true);
    }
}
