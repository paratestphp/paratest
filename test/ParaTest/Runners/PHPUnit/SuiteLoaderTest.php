<?php namespace ParaTest\Runners\PHPUnit;

class SuiteLoaderTest extends \TestBase
{
    protected $loader;
    protected $options;

    public function setUp()
    {
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

}