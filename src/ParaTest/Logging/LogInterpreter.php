<?php namespace ParaTest\Logging;

use ParaTest\Logging\JUnit\Reader;

class LogInterpreter
{
    protected $readers = array();

    public function addReader(Reader $reader)
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