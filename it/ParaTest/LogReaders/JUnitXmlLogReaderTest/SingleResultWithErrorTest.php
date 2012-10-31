<?php namespace ParaTest\LogReaders;

class JUnitXmlLogReaderTest_SingleResultWithErrorTest extends JUnitXmlLogReaderTest_BaseJUnitXmlLogReaderTestBase
{
    protected $fixture = 'single-werror.xml';
    protected $expectedTotalTests = 1;
    protected $expectedTotalAssertions = 0;
    protected $expectedTotalFailures = 0;
    protected $expectedTime = '0.002030';
    protected $expectedErrors = 1;
    protected $expectedSuiteName = 'UnitTestWithErrorTest';
    protected $expectedTestCases = array(
        array('pass' => false, 'failures' => 0, 'errors' => 1)
    );

    public function testGetErrors()
    {
        $errors = $this->reader->getErrors();

        $this->assertEquals('UnitTestWithErrorTest::testTruth
Exception: Error!!!

/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12', $errors[0]);
    }

}
