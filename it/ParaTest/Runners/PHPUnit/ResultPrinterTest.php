<?php namespace ParaTest\Runners\PHPUnit;

class ResultPrinterTest extends \TestBase
{
    protected $printer;
    protected $errorSuite;
    protected $failureSuite;
    protected $otherErrorSuite;
    protected $mixedSuite;
    protected $passingSuite;

    public function setUp()
    {
        $this->printer = new ResultPrinter();
        $this->errorSuite = $this->getSuiteWithResult('single-werror.xml');
        $this->otherErrorSuite = $this->getSuiteWithResult('single-werror2.xml');
        $this->failureSuite = $this->getSuiteWithResult('single-wfailure.xml');
        $this->mixedSuite = $this->getSuiteWithResult('mixed-results.xml');
        $this->passingSuite = $this->getSuiteWithResult('single-passing.xml');
    }

    public function testGetHeader()
    {
        $this->printer->addSuite($this->errorSuite)
                      ->addSuite($this->failureSuite);

        $header = $this->printer->getHeader();

        $this->assertRegExp("/\n\nTime: 0.007925, Memory:[\s][0-9]([.][0-9]{2})?Mb\n\n/", $header);
    }

    public function testGetErrorsSingleError()
    {
        $this->printer->addSuite($this->errorSuite)
                      ->addSuite($this->failureSuite);

        $errors = $this->printer->getErrors();

        $eq  = "There was 1 error:\n\n";
        $eq .= "1) UnitTestWithErrorTest::testTruth\n";
        $eq .= "Exception: Error!!!\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12\n";

        $this->assertEquals($eq, $errors);
    }

    public function testGetErrorsMultipleErrors()
    {
        $this->printer->addSuite($this->errorSuite)
                      ->addSuite($this->otherErrorSuite);

        $errors = $this->printer->getErrors();

        $eq  = "There were 2 errors:\n\n";
        $eq .= "1) UnitTestWithErrorTest::testTruth\n";
        $eq .= "Exception: Error!!!\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12\n";
        $eq .= "\n2) UnitTestWithOtherErrorTest::testSomeCase\n";
        $eq .= "Exception: Another Error!!!\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithOtherErrorTest.php:12\n";

        $this->assertEquals($eq, $errors);
    }

    public function testGetFailures()
    {
        $this->printer->addSuite($this->mixedSuite);

        $failures = $this->printer->getFailures();

        $eq  = "There were 2 failures:\n\n";
        $eq .= "1) UnitTestWithClassAnnotationTest::testFalsehood\n";
        $eq .= "Failed asserting that true is false.\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php:20\n";
        $eq .= "\n2) UnitTestWithMethodAnnotationsTest::testFalsehood\n";
        $eq .= "Failed asserting that true is false.\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php:18\n";

        $this->assertEquals($eq, $failures);        
    }

    public function testGetFooterWithFailures()
    {
        $this->printer->addSuite($this->errorSuite)
                      ->addSuite($this->mixedSuite);

        $footer = $this->printer->getFooter();

        $eq  = "\nFAILURES!\n";
        $eq .= "Tests: 8, Assertions: 6, Failures: 2, Errors: 2.\n";

        $this->assertEquals($eq, $footer);
    }

    public function testGetFooterWithSuccess()
    {
        $this->printer->addSuite($this->passingSuite);

        $footer = $this->printer->getFooter();

        $eq = "OK (3 tests, 3 assertions)\n";

        $this->assertEquals($eq, $footer);
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