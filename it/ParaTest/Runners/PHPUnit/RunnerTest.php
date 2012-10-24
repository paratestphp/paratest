<?php namespace ParaTest\Runners\PHPUnit;

class RunnerTest extends \TestBase
{
    protected $runner;
    protected $bootstrap;
    protected $path;

    protected static $descriptorspec = array(
       0 => array("pipe", "r"),
       1 => array("pipe", "w"),
       2 => array("pipe", "w")
    );

    public function setUp()
    {
        $this->runner = new Runner(array('path' => FIXTURES . DS . 'tests'));
        $this->path = FIXTURES . DS . 'tests';
        $this->bootstrap = dirname(FIXTURES) . DS . 'bootstrap.php';
    }

    public function testRunningTestsShouldLeaveNoTempFiles()
    {
        //dont want the output mucking up the test results
        ob_start();
        $this->runner->run();
        $output = ob_get_clean();
        $tempdir = sys_get_temp_dir();
        $output = glob($tempdir . DS . 'UnitTest*');
        $this->assertTrue(sizeof($output) == 0);
    }

    public function testRunningSuitesInParallelShouldBeFaster()
    {
        $time = '/Time: ([0-9]+(?:[.][0-9]+)?)/';
        preg_match($time, $this->getPhpunitOutput(), $smatches);
        preg_match($time, $this->getParaTestOutput(), $pmatches);
        $stdTime = $smatches[1];
        $paraTime = $pmatches[1];
        $msg = sprintf("PHPUnit: %s, ParaTest: %s", $stdTime, $paraTime);
        $this->assertTrue($paraTime < $stdTime, $msg);
    }

    protected function getPhpunitOutput()
    {
        $cmd = sprintf("phpunit --bootstrap %s %s", $this->bootstrap, $this->path);
        return $this->getTestOutput($cmd);
    }

    protected function getParaTestOutput()
    {
        $cmd = sprintf("%s --bootstrap %s --path %s", PARA_BINARY, $this->bootstrap, $this->path);
        return $this->getTestOutput($cmd);
    }

    public function getTestOutput($cmd)
    {
        $proc = proc_open($cmd, self::$descriptorspec, $pipes); 
        $this->waitForProc($proc);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($proc);
        return $output;
    }

    protected function waitForProc($proc)
    {
        $status = proc_get_status($proc);
        while($status['running'])
            $status = proc_get_status($proc);
    }
}