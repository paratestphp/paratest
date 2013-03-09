<?php namespace ParaTest\Logging\JUnit;

use ParaTest\Logging\MetaProvider;

class Reader extends MetaProvider
{
    protected $xml;
    protected $isSingle = false;
    protected $suites = array();

    protected $logFile;

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
        $this->xml = simplexml_load_file($this->logFile);
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

    public function getFeedback()
    {
        $feedback = '';
        $suites = $this->isSingle ? $this->suites : $this->suites[0]->suites;
        foreach($suites as $suite) {
            foreach($suite->cases as $case) {
                \ParaTest\Runners\PHPUnit\ResultPrinter::$casesProcessed++;
                $feedback .= $this->getCaseFeedback($case);
            }
        }
        return $feedback;
    }

    function getCaseFeedback($case)
    {
        $feedback = '';
        if ($case->failures)
            $feedback .= 'F';
        else if ($case->errors)
            $feedback .= 'E';
        else
            $feedback .= '.';

        $casesProcessed = \ParaTest\Runners\PHPUnit\ResultPrinter::$casesProcessed;
        $totalCases = \ParaTest\Runners\PHPUnit\ResultPrinter::$totalCases;
        $percent = floor(($casesProcessed * 100) / $totalCases);
        if (($casesProcessed % 100) == 0) {
            $feedback .= sprintf(
                ' %' . strlen($totalCases) . 'd / %' .
                       strlen($totalCases) . 'd (%3s%%)',

                $casesProcessed,
                $totalCases,
                $percent
              ) . PHP_EOL;
        }
        return $feedback;
    }

    public function removeLog()
    {
        unlink($this->logFile);
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