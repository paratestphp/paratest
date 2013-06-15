<?php namespace ParaTest\Logging;

use ParaTest\Logging\JUnit\Reader;
use ParaTest\ResultTester;

class LogInterpreterTest extends ResultTester
{
    protected $interpreter;

    public function setUp()
    {
        parent::setUp();
        $this->interpreter = new LogInterpreter();
        $this->interpreter
            ->addReader($this->getReader('mixedSuite'))
            ->addReader($this->getReader('passingSuite'));
    }

    public function testConstructor()
    {
        $interpreter = new LogInterpreter();
        $this->assertEquals(array(), $this->getObjectValue($interpreter, 'readers'));
    }

    public function testAddReaderIncrementsReaders()
    {
        $reader = $this->getMockReader();
        $this->interpreter->addReader($reader);
        $this->assertEquals(3, sizeof($this->getObjectValue($this->interpreter, 'readers')));
    }

    public function testAddReaderReturnsSelf()
    {
        $reader = $this->getMockReader();
        $self = $this->interpreter->addReader($reader);
        $this->assertSame($self, $this->interpreter);
    }

    public function testGetReaders()
    {
        $reader = $this->getMockReader();
        $this->interpreter->addReader($reader);
        $readers = $this->interpreter->getReaders();
        $this->assertEquals(3, sizeof($readers));
        $last = array_pop($readers);
        $this->assertSame($reader, $last);
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
        $interpreter = new LogInterpreter();
        $interpreter->addReader($this->getReader('failureSuite'));
        $this->assertFalse($interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseIfErrorsPresentAndNoFailures()
    {
        $interpreter = new LogInterpreter();
        $interpreter->addReader($this->getReader('errorSuite'));
        $this->assertFalse($interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseIfErrorsAndFailuresPresent()
    {
        $this->assertFalse($this->interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsTrueIfNoErrorsOrFailures()
    {
        $interpreter = new LogInterpreter();
        $interpreter->addReader($this->getReader('passingSuite'));
        $this->assertTrue($interpreter->isSuccessful());
    }

    public function testGetErrorsReturnsArrayOfErrorMessages()
    {
        $errors = array("UnitTestWithErrorTest::testTruth\nException: Error!!!\n\n/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12");
        $this->assertEquals($errors, $this->interpreter->getErrors());
    }

    public function testGetFailuresReturnsArrayOfFailureMessages()
    {
        $failures = array("UnitTestWithClassAnnotationTest::testFalsehood\nFailed asserting that true is false.\n\n/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php:20",
"UnitTestWithMethodAnnotationsTest::testFalsehood\nFailed asserting that true is false.\n\n/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php:18");
        $this->assertEquals($failures, $this->interpreter->getFailures());
    }

    public function testGetCasesReturnsAllCases()
    {
        $cases = $this->interpreter->getCases();
        $this->assertEquals(10, sizeof($cases));
    }

    public function testFlattenCasesReturnsCorrectNumberOfSuites()
    {
        $suites = $this->interpreter->flattenCases();
        $this->assertEquals(4, sizeof($suites));
        return $suites;
    }

    /**
     * @depends testFlattenCasesReturnsCorrectNumberOfSuites
     */
    public function testFlattenedSuiteHasCorrectTotals($suites)
    {
        $first = $suites[0];
        $this->assertEquals("UnitTestWithClassAnnotationTest", $first->name);
        $this->assertEquals("/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php", $first->file);
        $this->assertEquals('3', $first->tests);
        $this->assertEquals('3', $first->assertions);
        $this->assertEquals('1', $first->failures);
        $this->assertEquals('0', $first->errors);
        $this->assertEquals('0.006109', $first->time);
    }

    protected function getReader($suiteName)
    {
        return new Reader($this->$suiteName->getTempFile());
    }

    protected function getMockReader()
    {
        return $this->getMockBuilder('ParaTest\\Logging\\JUnit\\Reader')
                    ->disableOriginalConstructor()
                    ->getMock();
    }
}
