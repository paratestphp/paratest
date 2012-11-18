<?php namespace ParaTest\LogReaders\JUnit;

class TestCase
{
    public $name;
    public $class;
    public $file;
    public $line;
    public $assertions;
    public $time;

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
}