<?php namespace ParaTest\Runners\PHPUnit;

class SuiteLoaderTest extends \TestBase
{
    protected $loader;
    protected $options;

    public function setUp()
    {
        chdir(__DIR__);
        $this->options = new Options(array('group' => 'group1'));
        $this->loader = new SuiteLoader($this->options);
    }

    public function testConstructor()
    {
        $this->assertEquals($this->options, $this->getObjectValue($this->loader, 'options'));
    }

    public function testOptionsCanBeNull()
    {
        $loader = new SuiteLoader();
        $this->assertNull($this->getObjectValue($loader, 'options'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testOptionsMustBeInstanceOfOptionsIfNotNull()
    {
        $loader = new SuiteLoader(array('one' => 'two', 'three' => 'foure'));        
    }

    /**
     * @expectedException   \InvalidArgumentException
     */
    public function testLoadThrowsExceptionWithInvalidPath()
    {
        $this->loader->load('/path/to/nowhere');
    }

    /**
     * @expectedException   \RuntimeException
     * @expectedExceptionMessage No path or configuration provided
     */
    public function testLoadBarePathWithNoPathAndNoConfiguration()
    {
        $this->loader->load();
    }

    public function testLoadSuiteFromConfig()
    {
        $options = new Options(array('configuration' => FIXTURES . DS . 'phpunit.xml.dist'));
        $loader = new SuiteLoader($options);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');
        $this->assertEquals(10, sizeof($files));
    }

    /**
     * @expectedException   \RuntimeException
     * @expectedExceptionMessage Suite path ./nope/ could not be found
     */
    public function testLoadSuiteFromConfigWithBadSuitePath()
    {
        $options = new Options(array('configuration' => FIXTURES . DS . 'phpunitbad.xml.dist'));
        $loader = new SuiteLoader($options);
        $loader->load();
    }
}