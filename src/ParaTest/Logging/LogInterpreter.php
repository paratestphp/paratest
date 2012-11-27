<?php namespace ParaTest\Logging;

use ParaTest\Logging\JUnit\Reader,
    ParaTest\Logging\JUnit\TestSuite,
    ParaTest\Logging\MetaProvider;

class LogInterpreter extends MetaProvider
{
    protected $readers = array();

    public function rewind()
    {
        reset($this->readers);
    }

    public function addReader(Reader $reader)
    {
        $this->readers[] = $reader;
        return $this;
    }

    public function getReaders()
    {
        return $this->readers;
    }

    public function isSuccessful()
    {
        $failures = $this->getTotalFailures();
        $errors = $this->getTotalErrors();
        return $failures === 0 && $errors === 0;
    }

    public function getCases()
    {
        $cases = array();
        while(list( , $reader) = each($this->readers)) {
            foreach($reader->getSuites() as $suite) {
                $cases = array_merge($cases, $suite->cases);
                while(list( , $nested) = each($suite->suites))
                    $cases = array_merge($cases, $nested->cases);
            }
        }
        return $cases;
    }

    /**
     * Flattens all cases into their respective suites
     * @return array $suites a collection of suites and their cases
     */
    public function flattenCases()
    {
        $dict = array();
        foreach($this->getCases() as $case) {
            if(!isset($dict[$case->file])) $dict[$case->file] = new TestSuite($case->class, 0, 0, 0, 0, 0);
            $dict[$case->file]->cases[] = $case;
            $dict[$case->file]->tests += 1;
            $dict[$case->file]->assertions += $case->assertions;
            $dict[$case->file]->failures += sizeof($case->failures);
            $dict[$case->file]->errors += sizeof($case->errors);
            $dict[$case->file]->time += $case->time;
            $dict[$case->file]->file = $case->file;
        }
        return array_values($dict);
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