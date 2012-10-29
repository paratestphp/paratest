<?php

class PerformanceTest extends FunctionalTestBase
{
    protected static $testTime = '/Time: (([0-9]+)(?:[.][0-9]+)?)/';

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
}