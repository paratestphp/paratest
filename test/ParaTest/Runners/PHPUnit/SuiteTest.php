<?php namespace ParaTest\Runners\PHPUnit;

class SuiteTest extends \TestBase
{
    protected $suite;

    public function setUp()
    {
        $this->suite = new Suite('/path/to/UnitTest.php', array());
    }

    public function testConstruction()
    {
        $this->assertEquals(array(), $this->getObjectValue($this->suite, 'pipes'));
        $this->assertNull($this->getObjectValue($this->suite, 'process'));
    }
}