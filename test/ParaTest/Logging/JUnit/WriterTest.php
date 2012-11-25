<?php namespace ParaTest\Logging\JUnit;

use ParaTest\Logging\LogInterpreter;

class WriteTest extends \TestBase
{
    protected $writer;
    protected $interpreter;

    public function setUp()
    {
        $this->interpreter = new LogInterpreter();
        $this->writer = new Writer("test/fixtures/tests/", "/path/to/results.xml", $this->interpreter);
    }

    public function testConstructor()
    {
        $this->assertEquals("test/fixtures/tests/", $this->writer->getName());
        $this->assertEquals("/path/to/results.xml", $this->writer->getOutputPath());
        $this->assertInstanceOf('ParaTest\\Logging\\LogInterpreter', $this->getObjectValue($this->writer, 'interpreter'));
    }

    /*public function testSingleFileLog()
    {
        $passing = FIXTURES . DS . 'results' . DS . 'single-passing.xml';
        $reader = new Reader($passing);
        $this->interpreter->addReader($reader);
        $xml = $this->writer->getXml();
        $this->assertEquals(file_get_contents($passing), $xml);       
    }*/


}