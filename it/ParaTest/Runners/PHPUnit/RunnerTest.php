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

    protected static $testTime = '/Time: (([0-9]+)(?:[.][0-9]+)?)/';

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

    public function testRunningSuitesInParallelIsNotSlower()
    {
        list($stdTime, $paraTime, $msg) = $this->getExecTimes(
            $this->getPhpunitOutput(),
            $this->getParaTestOutput());
        $this->assertTrue($paraTime <= $stdTime, $msg);
    }

    public function testRunningLongRunningTestInParallelIsFaster()
    {
        $this->path = $this->path . DS . 'LongRunningTest.php';
        list($stdTime, $paraTime, $msg) = $this->getExecTimes(
            $this->getPhpunitOutput(),
            $this->getParaTestOutput(true));
        $this->assertTrue($paraTime < $stdTime, $msg);  
    }

    protected function getExecTimes($phpunitOut, $paraOut)
    {
        preg_match(self::$testTime, $phpunitOut, $smatches);
        preg_match(self::$testTime, $paraOut, $pmatches);
        $stdTime = $smatches[2];
        $paraTime = $pmatches[2];
        $msg = sprintf("PHPUnit: %s, ParaTest: %s", $stdTime, $paraTime);
        return array($stdTime, $paraTime, $msg);
    }

    protected function getPhpunitOutput()
    {
        $cmd = sprintf("phpunit --bootstrap %s %s", $this->bootstrap, $this->path);
        return $this->getTestOutput($cmd);
    }

    protected function getParaTestOutput($functional = false)
    {
        $cmd = sprintf("%s --bootstrap %s", PARA_BINARY, $this->bootstrap);
        if($functional) $cmd .= ' --functional';
        $cmd .= sprintf(" --path %s", $this->path);
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