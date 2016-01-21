<?php namespace ParaTest\Runners\PHPUnit;

/** 
 * The functionnalities of this class is tested in SuiteLoaderTest.php
 * 
 */
class TestFileLoaderTest extends \TestBase
{
    public function testConstructor()
    {
        $options = new Options(array('group' => 'group1'));
        $testFileLoader = new TestFileLoader($options);
        $this->assertEquals($options, $this->getObjectValue($testFileLoader, 'options'));
    }

    public function testOptionsCanBeNull()
    {
        $testFileLoader = new TestFileLoader();
        $this->assertNull($this->getObjectValue($testFileLoader, 'options'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testOptionsMustBeInstanceOfOptionsIfNotNull()
    {
        $testFileLoader = new TestFileLoader(array('one' => 'two', 'three' => 'four'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLoadThrowsExceptionWithInvalidPath()
    {
        $testFileLoader = new TestFileLoader();
        $testFileLoader->loadPath('path/to/nowhere');
    }
}
