<?php namespace ParaTest\LogReaders;

class JUnitXmlLogReaderTest_MixedResultsTest extends JUnitXmlLogReaderTest_BaseJUnitXmlLogReaderTestBase
{
    protected $fixture = 'mixed-results.xml';
    protected $expectedTotalTests = 7;
    protected $expectedTotalAssertions = 6;
    protected $expectedTotalFailures = 2;
    protected $expectedTime = '0.007625';
    protected $expectedErrors = 1;
    protected $expectedSuiteName = 'test/fixtures/tests/';

    protected $expectedTestCases = array(
        array('pass' => true, 'failures' => 0, 'errors' => 0),
        array('pass' => false, 'failures' => 1, 'errors' => 0),
        array('pass' => true, 'failures' => 0, 'errors' => 0),
        array('pass' => false, 'failures' => 0, 'errors' => 1),
        array('pass' => true, 'failures' => 0, 'errors' => 0),
        array('pass' => false, 'failures' => 1, 'errors' => 0),
        array('pass' => true, 'failures' => 0, 'errors' => 0)
    );

    /**
     * @expectedException   \InvalidArgumentException 
     */
    public function testConstructorWithInvalidLogPath()
    {
        $reader = new JUnitXmlLogReader('/path/to/nowhere');
    }

    public function testGetFailures()
    {
        $failures = $this->reader->getFailures();

        $this->assertEquals('UnitTestWithClassAnnotationTest::testFalsehood
Failed asserting that true is false.

/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php:20', $failures[0]);

        $this->assertEquals('UnitTestWithMethodAnnotationsTest::testFalsehood
Failed asserting that true is false.

/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php:18', $failures[1]);
    }

    public function testGetErrors()
    {
        $errors = $this->reader->getErrors();

        $this->assertEquals('UnitTestWithErrorTest::testTruth
Exception: Error!!!

/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12', $errors[0]);
    }
}