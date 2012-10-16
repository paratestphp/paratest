<?php namespace ParaTest\Runners\PHPUnit;

class RunnerTest extends \TestBase
{
    protected $runner;

    public function setUp()
    {
        $this->runner = new Runner(array('suite' => FIXTURES . DS . 'tests'));
        $this->runner->load();
    }

    public function testLoadShouldPopulatePendingCollection()
    {
        $loader = new SuiteLoader();
        $loader->loadDir(FIXTURES . DS . 'tests');

        $pending = $this->getObjectValue($this->runner, 'pending');
        $this->assertEquals($loader->getParallelSuites(), $pending);
    }

    public function testRunningTestsPopulatesTime()
    {
        $this->runner->run();
        $time = $this->getObjectValue($this->runner, 'time');
        $this->assertTrue($time > 0);
    }

    public function testRunningTestsShouldLeaveNoTempFiles()
    {
        $tempdir = sys_get_temp_dir();
        $output = glob($tempdir . DS . 'UnitTest*');
        $this->assertTrue(sizeof($output) == 0);
     }
}