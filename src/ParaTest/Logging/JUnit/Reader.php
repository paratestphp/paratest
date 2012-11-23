<?php namespace ParaTest\Logging\JUnit;

class Reader
{
    protected $xml;
    protected $isSingle = false;
    protected $suites = array();

    protected static $totalMethod = '/^getTotal([\w]+)$/';
    protected static $messageMethod = '/^get((Failure|Error)s)$/';

    protected static $defaultSuite = array('name' => '',
                                           'file' => '',
                                           'tests' => 0,
                                           'assertions' => 0,
                                           'failures' => 0,
                                           'errors' => 0,
                                           'time' => 0);

    public function __construct($logFile) 
    {
        if(!file_exists($logFile))
            throw new \InvalidArgumentException("Log file $logFile does not exist");

        $this->xml = simplexml_load_file($logFile);
        $this->init();
    }

    public function isSingleSuite()
    {
        return $this->isSingle;
    }

    public function getSuites()
    {
        return $this->suites;
    }

    /**
     * Simplify aggregation of totals or messages
     */
    public function __call($method, $args)
    {
        if(preg_match(self::$totalMethod, $method, $matches) && $property = strtolower($matches[1]))
            return $this->getNumericValue($property);
        if(preg_match(self::$messageMethod, $method, $matches) && $type = strtolower($matches[1]))
            return $this->getMessages($type);
    }

    public function getFeedback()
    {
        $feedback = '';
        $suites = $this->isSingle ? $this->suites : $this->suites[0]->suites;
        foreach($suites as $suite) {
            foreach($suite->cases as $case) {
                if($case->failures) $feedback .= 'F';
                else if ($case->errors) $feedback .= 'E';
                else $feedback .= '.';
            }
        }
        return $feedback;
    }

    protected function getNumericValue($property)
    {
       return ($property === 'time') 
              ? floatval($this->suites[0]->$property)
              : intval($this->suites[0]->$property);
    }

    protected function getMessages($type)
    {
        $messages = array();
        $suites = $this->isSingle ? $this->suites : $this->suites[0]->suites;
        foreach($suites as $suite)
            $messages = array_merge($messages, array_reduce($suite->cases, function($result, $case) use($type) {
                return array_merge($result, array_reduce($case->$type, function($msgs, $msg) { 
                    $msgs[] = $msg['text'];
                    return $msgs;
                }, array()));
            }, array()));
        return $messages;
    }

    protected function init()
    {
        $this->initSuite();
        $cases = $this->getCaseNodes();
        foreach($cases as $file => $nodeArray)
            $this->initSuiteFromCases($nodeArray);
    }

    /**
     * Uses an array of testcase nodes to build a suite
     * @param array $nodeArray an array of SimpleXMLElement nodes representing testcase elements
     */
    protected function initSuiteFromCases($nodeArray)
    {
        $testCases = array();
        $properties = $this->caseNodesToSuiteProperties($nodeArray, $testCases);
        if(!$this->isSingle) $this->addSuite($properties, $testCases);
        else $this->suites[0]->cases = $testCases;
    }

    protected function addSuite($properties, $testCases)
    {
        $suite = TestSuite::suiteFromArray($properties);
        $suite->cases = $testCases;
        $this->suites[0]->suites[] = $suite;
    }

    /**
     * Fold an array of testcase nodes into a suite array
     * @param array $nodeArray an array of testcase nodes
     * @param array $testCases an array reference. Individual testcases will be placed here.
     */
    protected function caseNodesToSuiteProperties($nodeArray, &$testCases = array())
    {
        $cb = array('ParaTest\\Logging\\JUnit\\TestCase', 'caseFromNode');
        return array_reduce($nodeArray, function($result, $c) use(&$testCases, $cb) {
            $testCases[] = call_user_func_array($cb, array($c));
            $result['name'] = (string)$c['class'];
            $result['file'] = (string)$c['file'];
            $result['tests'] = $result['tests'] + 1;
            $result['assertions'] += (int)$c['assertions'];
            $result['failures'] += sizeof($c->xpath('failure'));
            $result['errors'] += sizeof($c->xpath('error'));
            $result['time'] += floatval($c['time']);
            return $result;
        }, static::$defaultSuite);
    }

    protected function getCaseNodes()
    {
        $caseNodes = $this->xml->xpath('//testcase');
        $cases = array();
        while(list( , $node) = each($caseNodes)) {
            $case = $node;
            if(!isset($cases[(string)$node['file']])) $cases[(string)$node['file']] = array();
            $cases[(string)$node['file']][] = $node;
        }
        return $cases;
    }

    protected function initSuite()
    {
        $suiteNodes = $this->xml->xpath('/testsuites/testsuite/testsuite');
        $this->isSingle = sizeof($suiteNodes) === 0;
        $node = current($this->xml->xpath("/testsuites/testsuite"));
        $this->suites[] = TestSuite::suiteFromNode($node);
    }
}