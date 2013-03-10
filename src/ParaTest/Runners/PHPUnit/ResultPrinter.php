<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\Logging\LogInterpreter;
use ParaTest\Logging\JUnit\Reader;

class ResultPrinter
{
    protected $suites = array();
    protected $results;
    protected $numTestsWidth;
    protected $maxColumn;
    protected $totalCases = 0;
    protected $column = 0;
    protected $casesProcessed = 0;

    public function __construct(LogInterpreter $results)
    {
        $this->results = $results;
    }

    public function addTest(ExecutableTest $suite)
    {
        $this->suites[] = $suite;
        $increment = method_exists($suite, 'getFunctions') ? count($suite->getFunctions()) : 1;
        $this->totalCases = $this->totalCases + $increment;
        return $this;
    }

    public function start(Options $options)
    {
        $this->numTestsWidth = strlen((string) $this->totalCases);
        $this->maxColumn = 69 - (2 * $this->numTestsWidth);
        printf("\nRunning phpunit in %d process%s with %s%s\n\n",
               $options->processes,
               $options->processes > 1 ? 'es' : '',
               $options->phpunit,
               $options->functional ? '. Functional mode is on' : '');
        if(isset($options->filtered['configuration']))
            printf("Configuration read from %s\n\n", $options->filtered['configuration']->getPath());
        \PHP_Timer::start();
    }

    public function println($string = "")
    {
        $this->column = 0;
        print("$string\n");
    }

    public function flush()
    {
        $this->printResults();
        $this->clearLogs();
    }

    public function printResults()
    {
        print $this->getHeader();
        print $this->getErrors();
        print $this->getFailures();
        print $this->getFooter();
    }

    public function printFeedback(ExecutableTest $test)
    {
        $reader = new Reader($test->getTempFile());
        $this->results->addReader($reader);
        $feedbackItems = $reader->getFeedback();
        foreach ($feedbackItems as $item)
            $this->printFeedbackItem($item);
    }

    protected function printFeedbackItem($item)
    {
        print $item;
        $this->column++;
        $this->casesProcessed++;
        if ($this->column == $this->maxColumn)
            $this->printProgress();
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

    public function getTotalCases()
    {
        return $this->totalCases;
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

    protected function printProgress()
    {
        printf(
            ' %' . $this->numTestsWidth . 'd / %' .
                $this->numTestsWidth . 'd (%3s%%)',

            $this->casesProcessed,
            $this->totalCases,
            floor(($this->casesProcessed / $this->totalCases) * 100)
        );

        $this->println();
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