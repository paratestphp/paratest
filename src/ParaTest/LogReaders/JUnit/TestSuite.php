<?php namespace ParaTest\LogReaders\JUnit;

class TestSuite
{
    public $name;
    public $tests;
    public $assertions;
    public $failures;
    public $errors;
    public $time;
    public $file;
    public $suites = array();
    public $cases = array();

    public function __construct(
        $name, 
        $tests,
        $assertions,
        $failures,
        $errors,
        $time,
        $file = null)
    {
        $this->name = $name;
        $this->tests = $tests;
        $this->assertions = $assertions;
        $this->failures = $failures;
        $this->errors = $errors;
        $this->time = $time;
        $this->file = $file;
    }
}