<?php namespace ParaTest\LogReaders\JUnit;

class TestCase
{
    public $name;
    public $class;
    public $file;
    public $line;
    public $assertions;
    public $time;
    public $failures = array();
    public $errors = array();

    public function __construct(
        $name,
        $class,
        $file,
        $line,
        $assertions,
        $time)
    {
        $this->name = $name;
        $this->class = $class;
        $this->file = $file;
        $this->line = $line;
        $this->assertions = $assertions;
        $this->time = $time;
    }


    public function addFailure($type, $text)
    {
        $this->addDefect('failures', $type, $text);
    }

    public function addError($type, $text)
    {
        $this->addDefect('errors', $type, $text);
    }

    protected function addDefect($collName, $type, $text)
    {
        $this->{$collName}[] = array(
            'type' => $type,
            'text' => $text
        );
    }
}