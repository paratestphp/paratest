<?php

class DataProviderTest extends \PHPUnit\Framework\TestCase
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
        $result = [];
        for ($i = 0; $i < 50; $i++) {
            $result[] = [$i, $i];
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
        $result = [];
        for ($i = 0; $i < 50; $i++) {
            $name = 'name_of_test_' . $i;
            $result[$name] = [$i, $i];
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
        $result = [];
        for ($i = 0; $i < 1000; $i++) {
            $result[] = [$i, $i];
        }

        return $result;
    }

    /**
     * @dataProvider dataProviderIterable
     */
    public function testIterableDataProvider($expected, $actual)
    {
        $this->assertEquals($expected, $actual);
    }

    public function dataProviderIterable(): iterable
    {
        yield from $this->dataProviderNumeric50();
    }
}
