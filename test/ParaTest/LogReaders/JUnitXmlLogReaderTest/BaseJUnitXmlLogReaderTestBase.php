<?php namespace ParaTest\LogReaders;

abstract class JUnitXmlLogReaderTest_BaseJUnitXmlLogReaderTestBase extends \TestBase
{
    protected $reader;

    //override in children
    protected $fixture;
    protected $expectedTotalTests;
    protected $expectedTotalAssertions;
    protected $expectedTotalFailures;
    protected $expectedTime;
    protected $expectedErrors;

    public function setUp()
    {
        $fixturePath = 'results' . DS . $this->fixture;
        $fixture = $this->pathToFixture($fixturePath);
        $this->reader = new JUnitXmlLogReader($fixture);
    }

    public function testGetTotalTests()
    {
        $this->assertEquals($this->expectedTotalTests, $this->reader->getTotalTests());
    }

    public function testGetTotalAssertions()
    {
        $this->assertEquals($this->expectedTotalAssertions, $this->reader->getTotalAssertions());
    }

    public function testGetTotalFailures()
    {
        $this->assertEquals($this->expectedTotalFailures, $this->reader->getTotalFailures());
    }

    public function testGetTotalTime()
    {
        $this->assertEquals($this->expectedTime, $this->reader->getTotalTime());
    }

    public function testGetTotalErrors()
    {
        $this->assertEquals($this->expectedErrors, $this->reader->getTotalErrors());
    }

}