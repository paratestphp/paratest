<?php

class PerformanceTest extends FunctionalTestBase
{
    protected static $testTime = '/Time: (([0-9]+)(?:[.][0-9]+)?)/';

    public function testRunningSuitesInParallelIsNotSlower()
    {
        list($stdTime, $paraTime, $msg) = $this->getExecTimes(
            $this->getPhpunitOutput(),
            $this->getParaTestOutput());
        var_dump($stdTime, $paraTime);
        $this->assertTrue($paraTime <= $stdTime, $msg);
    }

    public function testRunningSuitesWithLongBootstrapsInParallelIsNotSlower()
    {
        $this->bootstrap = dirname(FIXTURES) . DS . 'slow_bootstrap.php';
        list($stdTime, $paraTime, $msg) = $this->getExecTimes(
            $this->getPhpunitOutput(),
            $this->getParaTestOutput());
        var_dump($stdTime, $paraTime);
        $this->assertTrue($paraTime <= $stdTime, $msg);
    }

    public function testRunningLotsOfShortTestsIsNotSlower()
    {
        $this->path = FIXTURES . DS . 'small-tests';
        exec("php {$this->path}/generate.php 100", $output);
        list($stdTime, $paraTime, $msg) = $this->getExecTimes(
            $this->getPhpunitOutput(),
            $this->getParaTestOutput());
        var_dump($stdTime, $paraTime);
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
        $stdTime = $this->getExecTime($phpunitOut);
        $paraTime = $this->getExecTime($paraOut);
        $msg = sprintf("PHPUnit: %s, ParaTest: %s", $stdTime, $paraTime);
        return array($stdTime, $paraTime, $msg);
    }

    private function getExecTime($output)
    {
        preg_match(self::$testTime, $output, $matches);
        if (!isset($matches[2])) {
            throw new RuntimeException("Cannot parse output: $output");
        } 
        return $matches[2];
    }
}
