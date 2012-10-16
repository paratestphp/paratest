<?php namespace ParaTest\Runners\PHPUnit;

class ResultPrinterTest extends \TestBase
{

    private function getSuiteWithResultSet($result)
    {
        $result = FIXTURES . DS . 'results' . $result;
        $suite = $this->getMockBuilder('Suite')
                      ->disableOriginalConstructor()
                      ->getMock();

        $suite->expects($this->any())
              ->method('getTempFile')
              ->will($this->returnValue($result));
    }
}