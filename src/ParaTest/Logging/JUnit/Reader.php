<?php

namespace ParaTest\Logging\JUnit;

use ParaTest\Logging\MetaProvider;

class Reader extends MetaProvider
{

    /**
     * @var TestSuite[]
     */
    protected $suites = array();

    /**
     * @var string
     */
    protected $logFile;

    public function __construct($logFile)
    {
        if(!file_exists($logFile)) {
            throw new \InvalidArgumentException("Log file $logFile does not exist");
        }

        $this->logFile = $logFile;
        if (filesize($logFile) == 0) {
            throw new \InvalidArgumentException("Log file $logFile is empty. This means a PHPUnit process has crashed.");
        }

        $logFileContents = file_get_contents($this->logFile);
        $this->init(new \SimpleXMLElement($logFileContents));
    }

    /**
     * @return TestSuite[]
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
        foreach ($this->suites as $suite) {
            if ($suite->isSubSuite) {
                $feedback[] = $this->validateCasesAsOne($suite->cases);
            } else {
                $feedback = array_merge($feedback, $this->validateCases($suite->cases));
            }
        }

        return $feedback;
    }

    /**
     * @param TestCase[] $cases
     *
     * @return array
     */
    private function validateCases($cases)
    {
        $results = array();
        foreach ($cases as $case) {
            $results[] = $case->failures ? 'F' : ($case->errors ? 'E' : '.');
        }

        return $results;
    }

    /**
     * @param TestCase[] $cases
     *
     * @return string
     */
    private function validateCasesAsOne($cases)
    {
        foreach ($cases as $case) {
            $result = $case->failures ? 'F' : ($case->errors ? 'E' : '.');
            if ($result !== '.') {
                return $result;
            }
        }

        return '.';
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
     *
     * @param \SimpleXMLElement $xml
     */
    private function init(\SimpleXMLElement $xml)
    {
        $baseSuite = current($xml->xpath('/testsuites/testsuite'));
        foreach ($baseSuite->testsuite as $testSuite) {
            $this->suites[] = $this->createSuite($testSuite);
        }

        if ($baseSuite->testcase) {
            $this->suites[] = $this->createSuite($baseSuite);
        }
    }

    /**
     * @param \SimpleXMLElement $element
     *
     * @return TestSuite
     */
    private function createSuite(\SimpleXMLElement $element) {
        $suite = TestSuite::suiteFromNode($element);
        $testCases = array();
        foreach ($element->testcase as $testCase) {
            $testCases[] = TestCase::caseFromNode($testCase);
        }
        $suite->cases = $testCases;

        return $suite;
    }

}
