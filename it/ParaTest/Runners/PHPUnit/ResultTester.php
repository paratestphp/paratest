<?php namespace ParaTest\Runners\PHPUnit;

abstract class ResultTester extends \TestBase
{
    protected $errorSuite;
    protected $failureSuite;
    protected $otherErrorSuite;
    protected $mixedSuite;
    protected $passingSuite;

    public function setUp()
    {
        $this->errorSuite = $this->getSuiteWithResult('single-werror.xml');
        $this->otherErrorSuite = $this->getSuiteWithResult('single-werror2.xml');
        $this->failureSuite = $this->getSuiteWithResult('single-wfailure.xml');
        $this->mixedSuite = $this->getSuiteWithResult('mixed-results.xml');
        $this->passingSuite = $this->getSuiteWithResult('single-passing.xml');
    }

    protected function getSuiteWithResult($result)
    {
        $result = FIXTURES . DS . 'results' . DS . $result;
        $suite = $this->getMockBuilder('ParaTest\\Runners\\PHPUnit\\Suite')
                      ->disableOriginalConstructor()
                      ->getMock();

        $suite->expects($this->any())
              ->method('getTempFile')
              ->will($this->returnValue($result));

        return $suite;
    }
}