<?php namespace ParaTest\LogReaders;

class JUnitXmlLogReaderTest_EmptyXmlTest extends \TestBase
{
    protected $reader;
        
    public function setUp()
    {
        $this->reader = new JUnitXmlLogReader(FIXTURES . DS . 'results' . DS . 'empty.xml');
    }

    public function testTestsDefaultsToZero()
    {
        $this->assertEquals(0, $this->reader->getTotalTests());
    }

    public function testAssertionsDefaultsToZero()
    {
        $this->assertEquals(0, $this->reader->getTotalAssertions());
    }

    public function testTotalFailuresDefaultsToZero()
    {
        $this->assertEquals(0, $this->reader->getTotalFailures());
    }

    public function testTotalErrorsDefaultsToZero()
    {
        $this->assertEquals(0, $this->reader->getTotalFailures());
    }

    public function testTotalTimeDefaultsToZero()
    {
        $this->assertEquals(0, $this->reader->getTotalTime());
    }
}