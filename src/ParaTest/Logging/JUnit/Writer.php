<?php namespace ParaTest\Logging\JUnit;

use ParaTest\Logging\LogInterpreter;

class Writer
{
    protected $name;
    protected $outputPath;
    protected $interpreter;

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
                                $outputPath,
                                $name = '')
    {
        $this->name = $name;
        $this->outputPath = $outputPath;
        $this->interpreter = $interpreter;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getOutputPath()
    {
        return $this->outputPath;
    }

    public function getXml()
    {
        $suites = $this->interpreter->flattenCases();
        $document = new \DOMDocument("1.0", "UTF-8");
        $root = $this->getSuiteRoot($document, $suites);
        foreach($suites as $suite) {
            $snode = $this->appendSuite($document, $root, $suite);
            foreach($suite->cases as $case) {
                $cnode = $this->appendCase($document, $snode, $case);
                foreach($case->failures as $failure) {
                    $fnode = $document->createElement("failure", $failure["text"] . "\n");
                    $fnode->setAttribute('type', $failure['type']);
                    $cnode->appendChild($fnode);
                }
                foreach($case->errors as $error) {
                    $enode = $document->createElement("error", $error["text"] . "\n");
                    $enode->setAttribute('type', $error['type']);
                    $cnode->appendChild($enode);
                }
            }
        }
        return $document->saveXML();
    }

    protected function appendSuite($document, $root, TestSuite $suite)
    {
        $suiteNode = $document->createElement("testsuite");
        $vars = get_object_vars($suite);
        foreach($vars as $name => $value) {
            if(preg_match(static::$suiteAttrs, $name))
                $suiteNode->setAttribute($name, $value);
        }
        $root->appendChild($suiteNode);
        return $suiteNode;
    }

    protected function appendCase($document, $suiteNode, TestCase $case)
    {
        $caseNode = $document->createElement("testcase");
        $vars = get_object_vars($case);
        foreach($vars as $name => $value) {
            if(preg_match(static::$caseAttrs, $name))
                $caseNode->setAttribute($name, $value);
        }
        $suiteNode->appendChild($caseNode);
        return $caseNode;
    }

    protected function getSuiteRoot($document, $suites)
    {
        $testsuites = $document->createElement("testsuites");
        $document->appendChild($testsuites);
        if(sizeof($suites) == 1) return $testsuites;
        $rootSuite = $document->createElement('testsuite');
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