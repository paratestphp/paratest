<?php
namespace ParaTest\LogReaders\JUnit;

class ReaderTest extends \TestBase
{
    protected $mixed;
    protected $single;

    public function setUp()
    {
        $mixed = FIXTURES . DS . 'results' . DS . 'mixed-results.xml';
        $single = FIXTURES . DS . 'results' . DS . 'single-wfailure.xml';
        $this->mixed = new Reader($mixed);
        $this->single = new Reader($single);
    }

    /**
     * @expectedException   \InvalidArgumentException
     */
    public function testInvalidPathThrowsException()
    {
        $reader = new Reader("/path/to/nowhere");
    }

    public function testIsSingleSuiteReturnsTrueForSingleSuite()
    {
        $this->assertTrue($this->single->isSingleSuite());   
    }

    public function testIsSingleSuiteReturnsFalseForMultipleSuites()
    {
        $this->assertFalse($this->mixed->isSingleSuite());
    }

    public function testMixedSuiteShouldConstructRootSuite()
    {
        $suites = $this->mixed->getSuites();
        $this->assertEquals(1, sizeof($suites));
        $this->assertEquals('test/fixtures/tests/', $suites[0]->name);
        $this->assertEquals('7', $suites[0]->tests);
        $this->assertEquals('6', $suites[0]->assertions);
        $this->assertEquals('2', $suites[0]->failures);
        $this->assertEquals('1', $suites[0]->errors);
        $this->assertEquals('0.007625', $suites[0]->time);
        return $suites[0];
    }

    /**
     * @depends testMixedSuiteShouldConstructRootSuite
     */
    public function testMixedSuiteConstructsChildSuites($suite)
    {
        $this->assertEquals(3, sizeof($suite->suites));
        $first = $suite->suites[0];
        $this->assertEquals('UnitTestWithClassAnnotationTest', $first->name);
        $this->assertEquals('/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php', $first->file);
        $this->assertEquals('3', $first->tests);
        $this->assertEquals('3', $first->assertions);
        $this->assertEquals('1', $first->failures);
        $this->assertEquals('0', $first->errors);
        $this->assertEquals('0.006109', $first->time);
        return $first;
    }

    /**
     * @depends testMixedSuiteConstructsChildSuites
     */
    public function testMixedSuiteConstructsTestCases($suite)
    {
        $this->assertEquals(3, sizeof($suite->cases));
        $first = $suite->cases[0];
        $this->assertEquals('testTruth', $first->name);
        $this->assertEquals('UnitTestWithClassAnnotationTest', $first->class);
        $this->assertEquals('/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php', $first->file);
        $this->assertEquals('10', $first->line);
        $this->assertEquals('1', $first->assertions);
        $this->assertEquals('0.001760', $first->time);
    }

    public function testMixedSuiteCasesLoadFailures()
    {
        $suites = $this->mixed->getSuites();
        $case = $suites[0]->suites[0]->cases[1];
        $this->assertEquals(1, sizeof($case->failures));
        $failure = $case->failures[0];
        $this->assertEquals('PHPUnit_Framework_ExpectationFailedException', $failure['type']);
        $this->assertEquals("UnitTestWithClassAnnotationTest::testFalsehood
Failed asserting that true is false.

/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php:20\n", $failure['text']);
    }

    public function testMixedSuiteCasesLoadErrors()
    {
        $suites = $this->mixed->getSuites();
        $case = $suites[0]->suites[1]->cases[0];
        $this->assertEquals(1, sizeof($case->errors));
        $error = $case->errors[0];
        $this->assertEquals('Exception', $error['type']);
        $this->assertEquals("UnitTestWithErrorTest::testTruth
Exception: Error!!!

/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12\n", $error['text']);
    }

    public function testSingleSuiteShouldConstructRootSuite()
    {
        $suites = $this->single->getSuites();
        $this->assertEquals(1, sizeof($suites));
        $this->assertEquals('UnitTestWithMethodAnnotationsTest', $suites[0]->name);
        $this->assertEquals("/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php", $suites[0]->file);
        $this->assertEquals('3', $suites[0]->tests);
        $this->assertEquals('3', $suites[0]->assertions);
        $this->assertEquals('1', $suites[0]->failures);
        $this->assertEquals('0', $suites[0]->errors);
        $this->assertEquals('0.005895', $suites[0]->time);
        return $suites[0];
    }

    /**
     * @depends testSingleSuiteShouldConstructRootSuite
     */
    public function testSingleSuiteShouldHaveNoChildSuites($suite)
    {
        $this->assertEquals(0, sizeof($suite->suites));
    }

    /**
     * @depends testSingleSuiteShouldConstructRootSuite
     */
    public function testSingleSuiteConstructsTestCases($suite)
    {
        $this->assertEquals(3, sizeof($suite->cases));
        $first = $suite->cases[0];
        $this->assertEquals('testTruth', $first->name);
        $this->assertEquals('UnitTestWithMethodAnnotationsTest', $first->class);
        $this->assertEquals('/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php', $first->file);
        $this->assertEquals('7', $first->line);
        $this->assertEquals('1', $first->assertions);
        $this->assertEquals('0.001632', $first->time);
    }

    public function testSingleSuiteCasesLoadFailures()
    {
        $suites = $this->single->getSuites();
        $case = $suites[0]->cases[1];
        $this->assertEquals(1, sizeof($case->failures));
        $failure = $case->failures[0];
        $this->assertEquals('PHPUnit_Framework_ExpectationFailedException', $failure['type']);
        $this->assertEquals("UnitTestWithMethodAnnotationsTest::testFalsehood
Failed asserting that true is false.

/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php:18\n", $failure['text']);
    }
}