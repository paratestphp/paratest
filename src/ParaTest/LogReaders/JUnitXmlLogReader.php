<?php namespace ParaTest\LogReaders;

class JUnitXmlLogReader
{
    private $xml;
    private $tests = 0;
    private $assertions = 0;
    private $failures = array();
    private $time = 0;
    private $errors = array();
    private $suiteName;
    private $testCases = array();

    public function __construct($logFile) 
    {
        if(!file_exists($logFile))
            throw new \InvalidArgumentException("Log file $logFile does not exist");

        $this->xml = simplexml_load_file($logFile);
        $this->initFromXml();
    }

    public function getTotalTests()
    {
        return $this->tests;
    }

    public function getTotalAssertions()
    {
        return $this->assertions;
    }

    public function getTotalFailures()
    {
        return sizeof($this->failures);
    }

    public function getFailures()
    {
        return $this->failures;
    }

    public function getTotalTime()
    {
        return $this->time;
    }

    public function getTotalErrors()
    {
        return sizeof($this->errors);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuiteName()
    {
        return $this->suiteName;
    }

    public function getTestCases()
    {
        return $this->testCases;
    }

    private function initFromXml()
    {
        $this->initFromRootSuite();
        $this->initFailures();
        $this->initErrors();
        $this->initTestCases();
    }

    private function initFromRootSuite()
    {
        $suite = $this->xml->xpath('/testsuites/testsuite[1]');
        $atts = $suite[0]->attributes();
        $this->tests = (string) $atts['tests'];
        $this->assertions = (string) $atts['assertions'];
        $this->time = (string) $atts['time'];
        $this->errors = (string) $atts['errors'];
        $this->suiteName = (string) $atts['name'];
    }

    private function initMessages(&$collection, $paths)
    {
        $collection = array();
        $nodes = $this->xml->xpath($paths[0]);
        if(sizeof($nodes) == 0)
            $nodes = $this->xml->xpath($paths[1]);
        while(list( , $node) = each($nodes))
            $collection[] = trim((string) $node);
    }

    private function initErrors()
    {
        $this->initMessages($this->errors, array(
            '/testsuites/testsuite/testsuite/testcase/error',
            '/testsuites/testsuite/testcase/error'
        ));
    }

    private function initFailures()
    {
        $this->initMessages($this->failures, array(
            '/testsuites/testsuite/testsuite/testcase/failure',
            '/testsuites/testsuite/testcase/failure'
        ));
    }

    private function initTestCases()
    {
        $cases = $this->xml->xpath('//testcase');
        if(sizeof($cases) === 0) return;
        while(list( , $case) = each($cases))
            $this->addTestCase($case);
    }

    private function addTestCase($node)
    {
        $failures = $node->xpath("failure");
        $errors = $node->xpath("error");
        $this->testCases[] = array(
            'pass' => (sizeof($failures) + sizeof($errors)) === 0,
            'failures' => sizeof($failures),
            'errors' => sizeof($errors)
        );
    }
}