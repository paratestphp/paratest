<?php namespace ParaTest\Logging\JUnit;

class WriteTest extends \TestBase
{
    public function testConstructor()
    {
        $writer = new Writer("test/fixtures/tests/", "/path/to/results.xml");
        $this->assertEquals("test/fixtures/tests/", $writer->getName());
        $this->assertEquals("/path/to/results.xml", $writer->getOutputPath());
    }
}