<?php namespace ParaTest\Runners\PHPUnit;

class ExecutableTestTest extends \TestBase
{
    protected $suite;

    public function setUp()
    {
        $this->suite = new Suite('/path/to/UnitTest.php', array());
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
}