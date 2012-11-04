<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\LogReaders\JUnitXmlLogReader;

class ResultPrinter
{
    protected $suites = array();
    protected $results;

    public function __construct()
    {
        $this->results = new ResultInterpreter();
    }

    public function addTest(ExecutableTest $suite)
    {
        $this->suites[] = $suite;
        return $this;
    }

    public function start(Options $options)
    {
        printf("\nRunning phpunit in %d process%s with %s%s\n\n",
               $options->processes,
               $options->processes > 1 ? 'es' : '',
               $options->phpunit,
               $options->functional ? '. Functional mode is on' : '');
        \PHP_Timer::start();
    }

    public function flush()
    {
        print $this->getHeader();
        print $this->getErrors();
        print $this->getFailures();
        print $this->getFooter();
        $this->clearLogs();
    }

    public function printFeedback(ExecutableTest $test)
    {
        $reader = new JUnitXmlLogReader($test->getTempFile());
        $cases = $reader->getTestCases();
        $this->results->addReader($reader);
        foreach($cases as $case)
            print $this->results->getCaseStatus($case);
    }

    public function getHeader()
    {
        return "\n\n" . \PHP_Timer::resourceUsage() . "\n\n";
    }

    public function getFooter()
    {
        return $this->results->isSuccessful()
                    ? $this->getSuccessFooter()
                    : $this->getFailedFooter(); 
    }

    public function getFailures()
    {
        $failures = $this->results->getFailures();
        return $this->getDefects($failures, 'failure');
    }

    public function getErrors()
    {
        $errors = $this->results->getErrors();
        return $this->getDefects($errors, 'error');
    }

    protected function getDefects($defects = array(), $type)
    {
        $count = sizeof($defects);
        if($count == 0) return '';
        $output = sprintf("There %s %d %s%s:\n",
            ($count == 1) ? 'was' : 'were',
            $count,
            $type,
            ($count == 1) ? '' : 's');

        for($i = 1; $i <= sizeof($defects); $i++)
            $output .= sprintf("\n%d) %s\n", $i, $defects[$i - 1]);

        return $output;
    }

    private function getFailedFooter()
    {
        $formatString = "\nFAILURES!\nTests: %d, Assertions: %d, Failures: %d, Errors: %d.\n";
        return sprintf($formatString, 
                       $this->results->getTotalTests(), 
                       $this->results->getTotalAssertions(), 
                       $this->results->getTotalFailures(), 
                       $this->results->getTotalErrors());
    }

    private function getSuccessFooter()
    {
        $tests = $this->results->getTotalTests();
        $asserts = $this->results->getTotalAssertions();
        return sprintf("OK (%d test%s, %d assertion%s)\n",
                       $tests,
                       ($tests == 1) ? '' : 's',
                       $asserts,
                       ($asserts == 1) ? '' : 's');
    }

    private function clearLogs()
    {
        //remove temporary logs
        foreach($this->suites as $suite)
            $suite->deleteFile();
    }
}