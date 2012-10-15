<?php namespace ParaTest\Runners\PHPUnit;

class SuiteTest extends \TestBase
{
    protected $suite;

    public function setUp()
    {
        $this->suite = new Suite('/path/to/UnitTest.php', array());
    }

    public function testConstructor()
    {
        $pipes = $this->getObjectValue($this->suite, 'pipes');
        $this->assertEquals(array(), $pipes);
        $resource = $this->getObjectValue($this->suite, 'resource');
        $this->assertNull($resource);
    }

    public function testGetTempFileShouldCreateTempFile()
    {
        $file = $this->suite->getTempFile();
        $this->assertTrue(file_exists($file));
        unlink($file);
    }

    public function testGetTempFileShouldReturnSameFileIfAlreadyCalled()
    {
        $file = $this->suite->getTempFile();
        $fileAgain = $this->suite->getTempFile();
        $this->assertEquals($file, $fileAgain);
        unlink($file);
    }

    public function testIsDoneReturnsFalseByDefault()
    {
        $this->assertFalse(false, $this->suite->isDone());
    }
}