<?php namespace ParaTest\Runners\PHPUnit;

class PHPUnitRunnerTest extends \TestBase
{
    protected $runner;
    protected $files;
    protected $testDir;

    public function setUp()
    {
        $this->runner = new Runner();
    }

    public function testConstructor()
    {
        $runner = new Runner(array('maxProcs' => 4, 'suite' => FIXTURES . DS . 'tests'));
        $loader = new SuiteLoader();
        $loader->loadDir(FIXTURES . DS . 'tests');

        $this->assertEquals(4, $this->getObjectValue($runner, 'maxProcs'));
        $this->assertEquals(FIXTURES . DS . 'tests', $this->getObjectValue($runner, 'suite'));
        $this->assertEquals(array(), $this->getObjectValue($runner, 'pending'));
        $this->assertEquals(array(), $this->getObjectValue($runner, 'running'));
        $this->assertEquals(0, $this->getObjectValue($runner, 'time'));
    }

    public function testDefaults()
    {
        $this->assertEquals(5, $this->getObjectValue($this->runner, 'maxProcs'));
        $this->assertEquals(getcwd(), $this->getObjectValue($this->runner, 'suite'));
    }
}