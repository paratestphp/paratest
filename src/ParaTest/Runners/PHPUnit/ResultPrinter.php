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
        $totalTime = array_reduce($this->readers(), function($result, $reader){
            $result += $reader->getTotalTime();
            return $result;
        }, 0);
        $peakUsage = memory_get_peak_usage(TRUE) / 1048576;
        return sprintf("\n\nTime: %f, Memory: %4.2fMb\n\n", $totalTime, $peakUsage);
    }

    public function getErrors()
    {
        $errors = array();
        foreach($this->readers() as $reader)
            $errors = array_merge($errors, $reader->getErrors());

        $num = sizeof($errors);
        $errString = sprintf("There %s %d error%s:\n",
            ($num == 1) ? 'was' : 'were',
            $num,
            ($num == 1) ? '' : 's');
        for($i = 1; $i <= sizeof($errors); $i++)
            $errString .= sprintf("\n%d) %s\n", $i, $errors[$i - 1]);

        return $errString;
    }

    private function readers()
    {
        if(empty($this->readers))
            foreach($this->suites as $suite)
                $this->readers[] = new JUnitXmlLogReader($suite->getTempFile());
        return $this->readers;
    }
}