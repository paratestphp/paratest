<?php namespace ParaTest\Logging\JUnit;

use ParaTest\Logging\MetaProvider;

class Reader extends MetaProvider
{
    /**
     * @var \SimpleXMLElement
     */
    protected $xml;

    /**
     * @var bool
     */
    protected $isSingle = false;

    /**
     * @var array
     */
    protected $suites = array();

    /**
     * @var string
     */
    protected $logFile;

    /**
     * @var array
     */
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

        $this->logFile = $logFile;
        if (filesize($logFile) == 0) {
            throw new \InvalidArgumentException("Log file $logFile is empty. This means a PHPUnit process has crashed.");
        }
        $logFileContents = file_get_contents($this->logFile);
        $this->xml = new \SimpleXMLElement($logFileContents);
        $this->init();
    }

    /**
     * Returns whether or not this reader contains only
     * a single suite
     *
     * @return bool
     */
    public function isSingleSuite()
    {
        return $this->isSingle;
    }

    /**
     * Return the Reader's collection
     * of test suites
     *
     * @return array
     */
    public function getSuites()
    {
        return $this->suites;
    }

    /**
     * Return an array that contains
     * each suite's instant feedback. Since
     * logs do not contain skipped or incomplete
     * tests this array will contain any number of the following
     * characters: .,F,E
     *
     * @return array
     */
    public function getFeedback()
    {
        $feedback = array();
        $suites = $this->isSingle ? $this->suites : $this->suites[0]->suites;
        foreach($suites as $suite) {
            foreach($suite->cases as $case) {
                if($case->failures) $feedback[] = 'F';
                else if ($case->errors) $feedback[] = 'E';
                else $feedback[] = '.';
            }
        }
        return $feedback;
    }

    /**
     * Remove the JUnit xml file
     */
    public function removeLog()
    {
        unlink($this->logFile);
    }

    /**
     * Initialize the suite collection
     * from the JUnit xml document
     */
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

    /**
     * Creates and adds a TestSuite based on the given
     * suite properties and collection of test cases
     *
     * @param $properties
     * @param $testCases
     */
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
     * @return mixed
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

    /**
     * Return a collection of testcase nodes
     * from the xml document
     *
     * @return array
     */
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

    /**
     * Determine if this reader is a single suite
     * and initialize the suite collection with the first
     * suite
     */
    protected function initSuite()
    {
        $suiteNodes = $this->xml->xpath('/testsuites/testsuite/testsuite');
        $this->isSingle = sizeof($suiteNodes) === 0;
        $node = current($this->xml->xpath("/testsuites/testsuite"));
        $this->suites[] = TestSuite::suiteFromNode($node);
    }
}
