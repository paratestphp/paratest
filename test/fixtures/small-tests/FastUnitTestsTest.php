<?php
class FastUnitTestsTest extends PHPUnit_Framework_TestCase
{
    public static function manyTests()
    {
        $total = array();
        for ($i = 0; $i < 1000; $i++) {
            $total[] = array(null);
        }
        return $total;
    }

    /**
     * @dataProvider manyTests
     */
    public function testShouldTakeAShortTimeByItself()
    {
        for ($i = 0; $i <= 20000; $i++) {}
        $this->assertTrue(true);
    }
}
