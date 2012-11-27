<?php namespace ParaTest\Logging\JUnit;

use ParaTest\Logging\LogInterpreter;

class WriteTest extends \TestBase
{
    protected $writer;
    protected $interpreter;

    public function setUp()
    {
        $this->interpreter = new LogInterpreter();
        $this->writer = new Writer($this->interpreter,  "/path/to/results.xml", "test/fixtures/tests/");
    }

    public function testConstructor()
    {
        $this->assertInstanceOf('ParaTest\\Logging\\LogInterpreter', $this->getObjectValue($this->writer, 'interpreter'));
        $this->assertEquals("/path/to/results.xml", $this->writer->getOutputPath());
        $this->assertEquals("test/fixtures/tests/", $this->writer->getName());
    }

    public function testSingleFileLog()
    {
        $passing = FIXTURES . DS . 'results' . DS . 'single-passing.xml';
        $reader = new Reader($passing);
        $this->interpreter->addReader($reader);
        $xml = $this->writer->getXml();
        $this->assertXmlStringEqualsXmlString(file_get_contents($passing), $xml);       
    }

    public function testMixedFileLog()
    {
        $mixed = FIXTURES . DS . 'results' . DS . 'mixed-results.xml';
        $reader = new Reader($mixed);
        $this->interpreter->addReader($reader);
        $writer = new Writer($this->interpreter, "/path/to/output.xml", "test/fixtures/tests/");
        $xml = $writer->getXml();
        $this->assertXmlStringEqualsXmlString(file_get_contents($mixed), $xml);
    }
}