<?php namespace ParaTest\Logging\JUnit;

use ParaTest\Logging\LogInterpreter;

class WriteTest extends \TestBase
{
    public function testConstructor()
    {
        $writer = new Writer("test/fixtures/tests/", "/path/to/results.xml", new LogInterpreter());
        $this->assertEquals("test/fixtures/tests/", $writer->getName());
        $this->assertEquals("/path/to/results.xml", $writer->getOutputPath());
        $this->assertInstanceOf('ParaTest\\Logging\\LogInterpreter', $this->getObjectValue($writer, 'interpreter'));
    }
}