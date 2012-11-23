<?php namespace ParaTest\Logging\JUnit;

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

    public static function suiteFromArray($arr)
    {
        return new TestSuite($arr['name'],
                             $arr['tests'],
                             $arr['assertions'],
                             $arr['failures'],
                             $arr['errors'],
                             $arr['time'],
                             $arr['file']);
    }

    public static function suiteFromNode(\SimpleXMLElement $node) 
    {
        return new TestSuite((string) $node['name'],
                             (string) $node['tests'],
                             (string) $node['assertions'],
                             (string) $node['failures'],
                             (string) $node['errors'],
                             (string) $node['time'],
                             (string) $node['file']);
    }
}