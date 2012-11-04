<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\LogReaders\JUnitXmlLogReader;

class ResultInterpreter
{
    protected $readers = array();

    public function addReader(JUnitXmlLogReader $reader)
    {
        $this->readers[] = $reader;
        return $this;
    }

    public function getErrors()
    {
        return $this->mergeMessages('getErrors');
    }

    public function getFailures()
    {
        return $this->mergeMessages('getFailures');
    }

    public function getTotalTests()
    {
        return $this->accumulate('getTotalTests');
    }

    public function getTotalAssertions()
    {
        return $this->accumulate('getTotalAssertions');
    }

    public function getTotalFailures()
    {
        return $this->accumulate('getTotalFailures');
    }

    public function getTotalErrors()
    {
        return $this->accumulate('getTotalErrors');
    }

    public function isSuccessful()
    {
        $failures = $this->getTotalFailures();
        $errors = $this->getTotalErrors();
        return $failures === 0 && $errors === 0;
    }

    /**
     * Returns the status indicator for a test case
     * @param array $case an associative array representing the status of a test case
     * An example would be
     * $case = array(
     *     'pass' => true,
     *     'errors' => 0,
     *     'failures' => 0
     * )
     * @return string $status a shot indication of the cases pass/fail/error status
     */
    public function getCaseStatus($case)
    {
        if($case['pass']) return '.';
        if($case['errors'] > 0) return 'E';
        else if ($case['failures'] > 0) return 'F';
    }

    private function mergeMessages($method)
    {
        $messages = array();
        foreach($this->readers as $reader)
            $messages = array_merge($messages, $reader->$method());
        return $messages;
    }

    private function accumulate($method)
    {
        return array_reduce($this->readers, function($result, $reader) use($method){
            $result += $reader->$method();
            return $result;
        }, 0);
    }
}