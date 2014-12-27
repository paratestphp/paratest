<?php

class UnitTestWithDataProviderAnnotationTest extends PHPUnit_Framework_TestCase
{

    public function testDummyTest()
    {
        $this->assertTrue(true);
    }

    /**
     * @dataProvider successDataProvider
     */
    public function testSuccess()
    {
        $this->assertTrue(true);
    }

    /**
     * @return array
     */
    public function successDataProvider()
    {
        return array(
            array($input = true,  $expected = true),
            array($input = 1,     $expected = 1),
            array($input = 'lol', $expected = 'lol'),
        );
    }

    /**
     * @dataProvider failureDataProvider
     */
    public function testFailure($input, $expected)
    {
        $this->assertSame($input, $expected);
    }

    /**
     * @return array
     */
    public function failureDataProvider()
    {
        return array(
            array($input = true,  $expected = true),
            array($input = 5,     $expected = 1), // not the same
            array($input = 'lol', $expected = 'lol'),
        );
    }

}
