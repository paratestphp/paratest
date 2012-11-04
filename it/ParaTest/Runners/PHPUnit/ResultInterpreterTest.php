<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\LogReaders\JUnitXmlLogReader;

class ResultInterpreterTest extends ResultTester
{
    protected $interpreter;

    public function setUp()
    {
        parent::setUp();
        $this->interpreter = new ResultInterpreter();
        $this->interpreter->addReader($this->getReader('mixedSuite'))
                          ->addReader($this->getReader('passingSuite'));
    }

    public function testGetTotalTests()
    {
        $this->assertEquals(10, $this->interpreter->getTotalTests());
    }

    public function testGetTotalAssertions()
    {
        $this->assertEquals(9, $this->interpreter->getTotalAssertions());
    }

    public function testGetTotalFailures()
    {
        $this->assertEquals(2, $this->interpreter->getTotalFailures());
    }

    public function testGetTotalErrors()
    {
        $this->assertEquals(1, $this->interpreter->getTotalErrors());
    }

    public function testIsSuccessfulReturnsFalseIfFailuresPresentAndNoErrors()
    {
        $interpreter = new ResultInterpreter();
        $interpreter->addReader($this->getReader('failureSuite'));
        $this->assertFalse($interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseIfErrorsPresentAndNoFailures()
    {
        $interpreter = new ResultInterpreter();
        $interpreter->addReader($this->getReader('errorSuite'));
        $this->assertFalse($interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseIfErrorsAndFailuresPresent()
    {
        $this->assertFalse($this->interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsTrueIfNoErrorsOrFailures()
    {
        $interpreter = new ResultInterpreter();
        $interpreter->addReader($this->getReader('passingSuite'));
        $this->assertTrue($interpreter->isSuccessful());
    }

    public function testGetErrorsReturnsArrayOfErrorMessages()
    {
        $errors = array('UnitTestWithErrorTest::testTruth
Exception: Error!!!

/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12');
        $this->assertEquals($errors, $this->interpreter->getErrors());      
    }

    public function testGetFailuresReturnsArrayOfFailureMessages()
    {
        $failures = array('UnitTestWithClassAnnotationTest::testFalsehood
Failed asserting that true is false.

/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php:20',
'UnitTestWithMethodAnnotationsTest::testFalsehood
Failed asserting that true is false.

/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php:18');
        $this->assertEquals($failures, $this->interpreter->getFailures());
    }

    protected function getReader($suiteName)
    {
        return new JUnitXmlLogReader($this->$suiteName->getTempFile());

    }
}