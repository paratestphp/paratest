<?php namespace ParaTest\Runners\PHPUnit;

class SuiteLoaderTest extends \TestBase
{
    protected $loader;
    protected $files;
    protected $testDir;

    public function setUp()
    {
        $this->loader = new SuiteLoader();
    }

    /**
     * @expectedException   \InvalidArgumentException
     */
    public function testLoadDirThrowsExceptionWithInvalidPath()
    {
        $this->loader->loadDir('/path/to/nowhere');
    }

}