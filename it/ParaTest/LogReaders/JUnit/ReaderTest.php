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
}