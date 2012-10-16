<?php namespace ParaTest\Runners\PHPUnit;

class ResultPrinterTest extends \TestBase
{
    protected $printer;
    protected $errorSuite;
    protected $failureSuite;

    public function setUp()
    {
        $this->printer = new ResultPrinter();
        $this->errorSuite = $this->getSuiteWithResult('single-werror.xml');
        $this->failureSuite = $this->getSuiteWithResult('single-wfailure.xml');
    }

    public function testGetHeader()
    {
        $this->printer->addSuite($this->errorSuite)
                      ->addSuite($this->failureSuite);

        $header = $this->printer->getHeader();

        $this->assertRegExp("/\n\nTime: 0.007925, Memory:[\s][0-9]([.][0-9]{2})?Mb\n\n/", $header);
    }

    public function testGetErrors()
    {
        $this->printer->addSuite($this->errorSuite)
                      ->addSuite($this->failureSuite);

        $errors = $this->printer->getErrors();

        $regEx  = "/There was 1 error:\n\n";
        $regEx .= "1\) UnitTestWithErrorTest::testTruth\n";
        $regEx .= "Exception: Error!!!\n\n";
        $regEx .= '\/home\/brian\/Projects\/parallel-phpunit\/test\/fixtures\/tests\/UnitTestWithErrorTest.php:12/';

        $this->assertRegExp($regEx, $errors);
    }

    private function getSuiteWithResult($result)
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