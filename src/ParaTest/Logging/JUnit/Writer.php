<?php namespace ParaTest\Logging\JUnit;

use ParaTest\Logging\LogInterpreter;

class Writer
{
    protected $name;
    protected $interpreter;
    protected $document;

    protected static $suiteAttrs = '/name|(?:test|assertion|failure|error)s|time|file/';
    protected static $caseAttrs = '/name|class|file|line|assertions|time/';
    protected static $defaultSuite = array(
                                        'tests' => 0,
                                        'assertions' => 0,
                                        'failures' => 0,
                                        'errors' => 0,
                                        'time' => 0
                                    );

    public function __construct(LogInterpreter $interpreter,
                                $name = '')
    {
        $this->name = $name;
        $this->interpreter = $interpreter;
        $this->document = new \DOMDocument("1.0", "UTF-8");
        $this->document->formatOutput = true;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getXml()
    {
        $suites = $this->interpreter->flattenCases();
        $root = $this->getSuiteRoot($suites);
        foreach($suites as $suite) {
            $snode = $this->appendSuite($root, $suite);
            foreach($suite->cases as $case)
                $cnode = $this->appendCase($snode, $case);
        }
        return $this->document->saveXML();
    }

    public function write($path)
    {
        file_put_contents($path, $this->getXml());
    }

    protected function appendSuite($root, TestSuite $suite)
    {
        $suiteNode = $this->document->createElement("testsuite");
        $vars = get_object_vars($suite);
        foreach($vars as $name => $value) {
            if(preg_match(static::$suiteAttrs, $name))
                $suiteNode->setAttribute($name, $value);
        }
        $root->appendChild($suiteNode);
        return $suiteNode;
    }

    protected function appendCase($suiteNode, TestCase $case)
    {
        $caseNode = $this->document->createElement("testcase");
        $vars = get_object_vars($case);
        foreach($vars as $name => $value)
            if(preg_match(static::$caseAttrs, $name)) $caseNode->setAttribute($name, $value);
        $suiteNode->appendChild($caseNode);
        $this->appendDefects($caseNode, $case->failures, 'failure');
        $this->appendDefects($caseNode, $case->errors, 'error');
        return $caseNode;
    }

    protected function appendDefects($caseNode, $defects, $type)
    {
        foreach($defects as $defect) {
            $defectNode = $this->document->createElement($type, $defect['text'] . "\n");
            $defectNode->setAttribute('type', $defect['type']);
            $caseNode->appendChild($defectNode);
        }
    }

    protected function getSuiteRoot($suites)
    {
        $testsuites = $this->document->createElement("testsuites");
        $this->document->appendChild($testsuites);
        if(sizeof($suites) == 1) return $testsuites;
        $rootSuite = $this->document->createElement('testsuite');
        $attrs = $this->getSuiteRootAttributes($suites);
        foreach($attrs as $attr => $value)
            $rootSuite->setAttribute($attr, $value);
        $testsuites->appendChild($rootSuite);
        return $rootSuite;
    }

    protected function getSuiteRootAttributes($suites)
    {
        return array_reduce($suites, function($result, $suite){
            $result['tests'] += $suite->tests;
            $result['assertions'] += $suite->assertions;
            $result['failures'] += $suite->failures;
            $result['errors'] += $suite->errors;
            $result['time'] += $suite->time;
            return $result;
        }, array_merge(array('name' => $this->name), self::$defaultSuite));
    }
}