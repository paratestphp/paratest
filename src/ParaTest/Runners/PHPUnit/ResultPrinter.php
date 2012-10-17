<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\LogReaders\JUnitXmlLogReader;

class ResultPrinter
{
    protected $suites = array();
    protected $time = 0;
    protected $readers = array();

    public function addSuite(Suite $suite)
    {
        $this->suites[] = $suite;
        return $this;
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

    public function getHeader()
    {
        $totalTime = $this->accumulate('getTotalTime');
        $peakUsage = memory_get_peak_usage(TRUE) / 1048576;
        return sprintf("\n\nTime: %f, Memory: %4.2fMb\n\n", $totalTime, $peakUsage);
    }

    public function getFooter()
    {
        $tests = $this->accumulate('getTotalTests');
        $assertions = $this->accumulate('getTotalAssertions');
        $failures = $this->accumulate('getTotalFailures');
        $errors = $this->accumulate('getTotalErrors');        
        return sprintf(
                "\nFAILURES!\nTests: %d, Assertions: %d, Failures: %d, Errors: %d.\n",
                $tests, $assertions, $failures, $errors);
    }

    public function getFailures()
    {
        $failures = array();
        foreach ($this->readers() as $reader)
            $failures = array_merge($failures, $reader->getFailures());

        return $this->getDefects($failures, 'failure');
    }

    public function getErrors()
    {
        $errors = array();
        foreach($this->readers() as $reader)
            $errors = array_merge($errors, $reader->getErrors());

        return $this->getDefects($errors, 'error');
    }

    protected function getDefects($defects = array(), $type)
    {
        $count = sizeof($defects);
        if($count == 0) return '';
        $output = sprintf("There %s %d %s%s:\n",
            ($count == 1) ? 'was' : 'were',
            $count,
            $type,
            ($count == 1) ? '' : 's');

        for($i = 1; $i <= sizeof($defects); $i++)
            $output .= sprintf("\n%d) %s\n", $i, $defects[$i - 1]);

        return $output;
    }

    private function readers()
    {
        if(empty($this->readers))
            foreach($this->suites as $suite)
                $this->readers[] = new JUnitXmlLogReader($suite->getTempFile());
        return $this->readers;
    }

    private function accumulate($method)
    {
        return array_reduce($this->readers(), function($result, $reader) use($method){
            $result += $reader->$method();
            return $result;
        }, 0);
    }
}