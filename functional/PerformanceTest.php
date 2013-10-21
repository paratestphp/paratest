<?php

class PerformanceTest extends FunctionalTestBase
{
    protected static $testTimeSeconds = '/Time: (([0-9]+)(?:[.][0-9]+)?) second[s]?,/';
    protected static $testTimeMilliseconds = '/Time: (([0-9]+)(?:[.][0-9]+)?) ms,/';

    public function setUp()
    {
        parent::setUp();
        $this->deleteSmallTests();
    }

    public function testRunningSuitesInParallelIsNotSlower()
    {
        list($stdTime, $paraTime, $msg) = $this->getExecTimes(
            $this->getPhpunitOutput(),
            $this->getParaTestOutput());
        $this->assertTrue($paraTime <= $stdTime, $msg);
    }

    public function testRunningSuitesWithLongBootstrapsIsFasterWithTheWrapperRunner()
    {
        $this->bootstrap = dirname(FIXTURES) . DS . 'slow_bootstrap.php';
        list($paraTime, $wrapperParaTime, $msg) = $this->getExecTimes(
            $output = $this->getParaTestOutput(false, array(
                'runner' => 'Runner',
                'processes' => 2
            )),
            $output = $this->getParaTestOutput(false, array(
                'runner' => 'WrapperRunner',
                'processes' => 2
            ))
        );
        $this->assertTrue($wrapperParaTime <= $paraTime, $msg);
    }

    public function testRunningSuitesWithLongBootstrapsIsReliablyFasterThanVanillaPhpunit()
    {
        $this->markTestIncomplete("Currently the execution times are comparable.");
        $this->bootstrap = dirname(FIXTURES) . DS . 'slow_bootstrap.php';
        list($stdTime, $paraTime, $msg) = $this->getExecTimes(
            $this->getPhpunitOutput(),
            $this->getParaTestOutput(false, array(
                'runner' => 'WrapperRunner',
                'processes' => 2
            ))
        );
        $this->assertTrue($paraTime <= $stdTime, $msg);
    }

    public function testRunningLotsOfShortTestsIsFasterWithTheWrapperRunner()
    {
        $this->path = FIXTURES . DS . 'small-tests';
        $this->createSmallTests(50);
        list($paraTime, $wrapperParaTime, $msg) = $this->getExecTimes(
            $this->getParaTestOutput(false, array(
                'runner' => 'Runner',
            )),
            $this->getParaTestOutput(false, array(
                'runner' => 'WrapperRunner',
            ))
        );
        $this->assertTrue($wrapperParaTime <= $paraTime, $msg);
    }

    public function testRunningLotsOfShortTestsIsReliablyFasterThanWithVanillaPhpunit()
    {
        $this->markTestIncomplete("Currently the execution times are comparable.");
        $this->path = FIXTURES . DS . 'small-tests';
        $this->createSmallTests(50);
        list($stdTime, $paraTime, $msg) = $this->getExecTimes(
            $this->getPhpunitOutput(),
            $this->getParaTestOutput(false, array(
                'runner' => 'WrapperRunner',
            ))
        );
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
        preg_match(self::$testTimeSeconds, $output, $matches);
        if (!isset($matches[2])) {
            preg_match(self::$testTimeMilliseconds, $output, $matches);
            if (!isset($matches[2])) {
                throw new RuntimeException("Cannot parse output: {var_export($output)}. {$this->debugInformation()}");
            }
            $matches[2] = $matches[2] / 1000.0;
        } 
        return $matches[2];
    }
}
