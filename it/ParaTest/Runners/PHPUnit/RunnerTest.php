<?php namespace ParaTest\Runners\PHPUnit;

class RunnerTest extends \TestBase
{
    protected $runner;

    public function setUp()
    {
        $this->runner = new Runner(array(
            'path' => FIXTURES . DS . 'tests',
            'phpunit' => PHPUNIT,
            'bootstrap' => BOOTSTRAP
        ));
    }

    public function testRunningTestsShouldLeaveNoTempFiles()
    {
        //dont want the output mucking up the test results
        ob_start();
        $this->runner->run();
        $output = ob_get_clean();
        $tempdir = sys_get_temp_dir();
        $output = glob($tempdir . DS . 'PT_*');
        $this->assertTrue(sizeof($output) == 0);
    }
}