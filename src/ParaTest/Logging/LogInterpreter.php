<?php namespace ParaTest\Logging;

use ParaTest\Logging\JUnit\Reader,
    ParaTest\Logging\MetaProvider;

class LogInterpreter extends MetaProvider
{
    protected $readers = array();

    public function addReader(Reader $reader)
    {
        $this->readers[] = $reader;
        return $this;
    }

    public function getReaders()
    {
        return $this->readers;
    }

    protected function getNumericValue($property)
    {
       return ($property === 'time') 
              ? floatval($this->accumulate('getTotalTime'))
              : intval($this->accumulate('getTotal' . ucfirst($property)));
    }

    protected function getMessages($type)
    {
        return $this->mergeMessages('get' . ucfirst($type));
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