<?php namespace ParaTest\Logging\JUnit;

use ParaTest\Logging\LogInterpreter;

class Writer
{
    /**
     * The name attribute of the testsuite being
     * written
     *
     * @var string
     */
    protected $name;

    /**
     * @var \ParaTest\Logging\LogInterpreter
     */
    protected $interpreter;

    /**
     * @var \DOMDocument
     */
    protected $document;

    /**
     * A pattern for matching testsuite attributes
     *
     * @var string
     */
    protected static $suiteAttrs = '/name|(?:test|assertion|failure|error)s|time|file/';

    /**
     * A pattern for matching testcase attrs
     *
     * @var string
     */
    protected static $caseAttrs = '/name|class|file|line|assertions|time/';

    /**
     * A default suite to ease flattening of
     * suite structures
     *
     * @var array
     */
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

    /**
     * Get the name of the root suite being written
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the xml structure the writer
     * will use
     *
     * @return string
     */
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

    /**
     * Write the xml structure to a file path
     *
     * @param $path
     */
    public function write($path)
    {
        file_put_contents($path, $this->getXml());
    }

    /**
     * Append a testsuite node to the given
     * root element
     *
     * @param $root
     * @param TestSuite $suite
     * @return \DOMElement
     */
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

    /**
     * Append a testcase node to the given testsuite
     * node
     *
     * @param $suiteNode
     * @param TestCase $case
     * @return \DOMElement
     */
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

    /**
     * Append error or failure nodes to the given testcase node
     *
     * @param $caseNode
     * @param $defects
     * @param $type
     */
    protected function appendDefects($caseNode, $defects, $type)
    {
        foreach($defects as $defect) {
            $defectNode = $this->document->createElement($type, $defect['text'] . "\n");
            $defectNode->setAttribute('type', $defect['type']);
            $caseNode->appendChild($defectNode);
        }
    }

    /**
     * Get the root level testsuite node
     *
     * @param $suites
     * @return \DOMElement
     */
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

    /**
     * Get the attributes used on the root testsuite
     * node
     *
     * @param $suites
     * @return mixed
     */
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