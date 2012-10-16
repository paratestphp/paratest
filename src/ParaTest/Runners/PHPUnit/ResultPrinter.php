<?php namespace ParaTest\Runners\PHPUnit;

class ResultPrinter
{
    protected $suites = array();
    protected $time = 0;

    public function addSuite(Suite $suite)
    {
        $this->suites[] = $suite;
    }

    public function startTimer()
    {
        $this->time = microtime(true);
    }

    public function getTime()
    {
        $total = microtime(true) - $this->time;
        $this->time = 0;
        return $total;
    }
}