<?php namespace ParaTest\Runners\PHPUnit;

class ResultPrinterTest extends ResultTester
{
    protected $printer;

    public function setUp()
    {
        parent::setUp();
        $this->printer = new ResultPrinter();
    }

    public function testGetHeader()
    {
        $this->printer->addTest($this->errorSuite)
                      ->addTest($this->failureSuite);

        $this->prepareReaders();

        $header = $this->printer->getHeader();

        $this->assertRegExp("/\n\nTime: [0-9]+ seconds?, Memory:[\s][0-9]([.][0-9]{2})?Mb\n\n/", $header);
    }

    public function testGetErrorsSingleError()
    {
        $this->printer->addTest($this->errorSuite)
                      ->addTest($this->failureSuite);

        $this->prepareReaders();

        $errors = $this->printer->getErrors();

        $eq  = "There was 1 error:\n\n";
        $eq .= "1) UnitTestWithErrorTest::testTruth\n";
        $eq .= "Exception: Error!!!\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12\n";

        $this->assertEquals($eq, $errors);
    }

    public function testGetErrorsMultipleErrors()
    {
        $this->printer->addTest($this->errorSuite)
                      ->addTest($this->otherErrorSuite);

        $this->prepareReaders();

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
        $this->printer->addTest($this->mixedSuite);

        $this->prepareReaders();

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
        $this->printer->addTest($this->errorSuite)
                      ->addTest($this->mixedSuite);

        $this->prepareReaders();

        $footer = $this->printer->getFooter();

        $eq  = "\nFAILURES!\n";
        $eq .= "Tests: 8, Assertions: 6, Failures: 2, Errors: 2.\n";

        $this->assertEquals($eq, $footer);
    }

    public function testGetFooterWithSuccess()
    {
        $this->printer->addTest($this->passingSuite);

        $this->prepareReaders();

        $footer = $this->printer->getFooter();

        $eq = "OK (3 tests, 3 assertions)\n";

        $this->assertEquals($eq, $footer);
    }

    public function testPrintFeedbackForMixed()
    {
        ob_start();
        $this->printer->printFeedback($this->mixedSuite);
        $contents = ob_get_clean();
        $this->assertEquals('.F.E.F.', $contents);
    }

    private function prepareReaders()
    {
        $suites = $this->getObjectValue($this->printer, 'suites');
        ob_start();
        foreach($suites as $suite)
            $this->printer->printFeedback($suite);
        ob_clean();
    }
}