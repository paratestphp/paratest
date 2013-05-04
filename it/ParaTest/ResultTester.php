<?php namespace ParaTest;

use ParaTest\Parser\ParsedFunction;

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

    public function getSuiteWithResult($result)
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

    public function mockFunctions($mockSuite, $numFuncs)
    {
        $i = 0;
        $funcs = array();
        while ($i < $numFuncs) {
            $funcs[] = new ParsedFunction('doc', 'public', 'func' + $i);
            $i++;
        }
        $mockSuite->expects($this->any())
                  ->method('getFunctions')
                  ->will($this->returnValue($funcs));
    }
}