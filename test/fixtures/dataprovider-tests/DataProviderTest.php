<?php

class DataProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dataProviderNumeric50
     */
    public function testNumericDataProvider50($expected, $actual)
    {
        $this->assertEquals($expected, $actual);
    }

    public function dataProviderNumeric50()
    {
        $result = array();
        for ($i = 0; $i < 50; $i++) {
            $result[] = array($i, $i);
        }

        return $result;
    }

    /**
     * @dataProvider dataProviderNamed50
     */
    public function testNamedDataProvider50($expected, $actual)
    {
        $this->assertEquals($expected, $actual);
    }

    public function dataProviderNamed50()
    {
        $result = array();
        for ($i = 0; $i < 50; $i++) {
            $name = "name_of_test_" . $i;
            $result[$name] = array($i, $i);
        }

        return $result;
    }

    /**
     * @dataProvider dataProviderNumeric1000
     */
    public function testNumericDataProvider1000($expected, $actual)
    {
        $this->assertEquals($expected, $actual);
    }

    public function dataProviderNumeric1000()
    {
        $result = array();
        for ($i = 0; $i < 1000; $i++) {
            $result[] = array($i, $i);
        }

        return $result;
    }
}
