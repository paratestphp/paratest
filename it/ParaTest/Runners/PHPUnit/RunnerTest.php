<?php namespace ParaTest\Runners\PHPUnit;

class RunnerTest extends \TestBase
{
    protected $runner;

    public function setUp()
    {
        $this->runner = new Runner(array('path' => FIXTURES . DS . 'tests'));
    }

    public function testRunningTestsShouldLeaveNoTempFiles()
    {
        $this->runner->run();
        $tempdir = sys_get_temp_dir();
        $output = glob($tempdir . DS . 'UnitTest*');
        $this->assertTrue(sizeof($output) == 0);
     }
}