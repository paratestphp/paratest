<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\LogReaders\JUnitXmlLogReader;

class ResultPrinter
{
    protected $suites = array();
    protected $time = 0;
    protected $readers = array();

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

    public function printOutput()
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
        foreach($cases as $case) {
            if($case['pass']) print '.';
            if($case['errors'] > 0) print 'E';
            else if ($case['failures'] > 0) print 'F';
        }
        $this->readers[] = $reader;
    }

    public function getHeader()
    {
        return "\n\n" . \PHP_Timer::resourceUsage() . "\n\n";
    }

    public function getFooter()
    {
        $tests = $this->accumulate('getTotalTests');
        $assertions = $this->accumulate('getTotalAssertions');
        $failures = $this->accumulate('getTotalFailures');
        $errors = $this->accumulate('getTotalErrors');
        return $this->isSuccessful($failures, $errors)
                    ? $this->getSuccessFooter($tests, $assertions)
                    : $this->getFailedFooter($tests, $assertions, $failures, $errors); 
    }

    public function getFailures()
    {
        $failures = array();
        foreach ($this->readers as $reader)
            $failures = array_merge($failures, $reader->getFailures());

        return $this->getDefects($failures, 'failure');
    }

    public function getErrors()
    {
        $errors = array();
        foreach($this->readers as $reader)
            $errors = array_merge($errors, $reader->getErrors());

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

    private function getFailedFooter($tests, $assertions, $failures, $errors)
    {
        $formatString = "\nFAILURES!\nTests: %d, Assertions: %d, Failures: %d, Errors: %d.\n";
        return sprintf($formatString, $tests, $assertions, $failures, $errors);
    }

    private function getSuccessFooter($tests, $asserts)
    {
        return sprintf("OK (%d test%s, %d assertion%s)\n",
                       $tests,
                       ($tests == 1) ? '' : 's',
                       $asserts,
                       ($asserts == 1) ? '' : 's');
    }

    private function isSuccessful($failures, $errors)
    {
        return $failures == 0 && $errors == 0;
    }

    private function accumulate($method)
    {
        return array_reduce($this->readers, function($result, $reader) use($method){
            $result += $reader->$method();
            return $result;
        }, 0);
    }

    private function clearLogs()
    {
        //remove temporary logs
        foreach($this->suites as $suite)
            $suite->deleteFile();
    }
}