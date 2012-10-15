<?php namespace ParaTest\Runners\PHPUnit;

class RunnerTest extends \TestBase
{
    protected $runner;

    public function setUp()
    {
        $this->runner = new Runner(array('suite' => FIXTURES . DS . 'tests'));
    }

    public function testLoadShouldPopulatePendingCollection()
    {
        $loader = new SuiteLoader();
        $loader->loadDir(FIXTURES . DS . 'tests');

        $this->runner->load();

        $pending = $this->getObjectValue($this->runner, 'pending');
        $this->assertEquals($loader->getParallelSuites(), $pending);
    }
}