<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\Logging\LogInterpreter;
use ParaTest\Logging\JUnit\Reader;

/**
 * Class ResultPrinter
 *
 * Used for outputing ParaTest results
 *
 * @package ParaTest\Runners\PHPUnit
 */
class ResultPrinter
{
    /**
     * A collection of ExecutableTest objects
     *
     * @var array
     */
    protected $suites = array();

    /**
     * @var \ParaTest\Logging\LogInterpreter
     */
    protected $results;

    /**
     * The number of tests results currently printed.
     * Used to determine when to tally current results
     * and start a new row
     *
     * @var int
     */
    protected $numTestsWidth;

    /**
     * Used for formatting results to a given width
     *
     * @var int
     */
    protected $maxColumn;

    /**
     * The total number of cases to be run
     *
     * @var int
     */
    protected $totalCases = 0;

    /**
     * The current column being printed to
     *
     * @var int
     */
    protected $column = 0;

    /**
     * @var \PHP_Timer
     */
    protected $timer;

    /**
     * The total number of cases printed so far
     *
     * @var int
     */
    protected $casesProcessed = 0;

    /**
     * Whether to display a red or green bar
     *
     * @var bool
     */
    protected $colors;

    public function __construct(LogInterpreter $results)
    {
        $this->results = $results;
        $this->timer = new \PHP_Timer();
    }

    /**
     * Adds an ExecutableTest to the tracked results
     *
     * @param ExecutableTest $suite
     * @return $this
     */
    public function addTest(ExecutableTest $suite)
    {
        $this->suites[] = $suite;
        $increment = method_exists($suite, 'getFunctions') ? count($suite->getFunctions()) : 1;
        $this->totalCases = $this->totalCases + $increment;

        return $this;
    }

    /**
     * Initializes printing constraints, prints header
     * information and starts the test timer
     *
     * @param Options $options
     */
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
        $this->timer->start();
        $this->colors = $options->colors;
    }

    /**
     * @param string $string
     */
    public function println($string = "")
    {
        $this->column = 0;
        print("$string\n");
    }

    /**
     * Prints all results and removes any log files
     * used for aggregating results
     */
    public function flush()
    {
        $this->printResults();
        $this->clearLogs();
    }

    /**
     * Print final results
     */
    public function printResults()
    {
        print $this->getHeader();
        print $this->getErrors();
        print $this->getFailures();
        print $this->getFooter();
    }

    /**
     * Prints the individual "quick" feedback for run
     * tests, that is the ".EF" items
     *
     * @param ExecutableTest $test
     */
    public function printFeedback(ExecutableTest $test)
    {
        $reader = new Reader($test->getTempFile());
        $this->results->addReader($reader);
        $feedbackItems = $reader->getFeedback();
        foreach ($feedbackItems as $item)
            $this->printFeedbackItem($item);
    }

    /**
     * Prints a single "quick" feedback item and increments
     * the total number of processed cases and the column
     * position
     *
     * @param $item
     */
    protected function printFeedbackItem($item)
    {
        print $item;
        $this->column++;
        $this->casesProcessed++;
        if ($this->column == $this->maxColumn)
            $this->printProgress();
    }

    /**
     * Returns the header containing resource usage
     *
     * @return string
     */
    public function getHeader()
    {
        return "\n\n" . $this->timer->resourceUsage() . "\n\n";
    }

    /**
     * Return the footer information reporting success
     * or failure
     *
     * @return string
     */
    public function getFooter()
    {
        return $this->results->isSuccessful()
                    ? $this->getSuccessFooter()
                    : $this->getFailedFooter();
    }

    /**
     * Returns the failure messages
     *
     * @return string
     */
    public function getFailures()
    {
        $failures = $this->results->getFailures();

        return $this->getDefects($failures, 'failure');
    }

    /**
     * Returns error messages
     *
     * @return string
     */
    public function getErrors()
    {
        $errors = $this->results->getErrors();

        return $this->getDefects($errors, 'error');
    }

    /**
     * Returns the total cases being printed
     *
     * @return int
     */
    public function getTotalCases()
    {
        return $this->totalCases;
    }

    /**
     * Method that returns a formatted string
     * for a collection of errors or failures
     *
     * @param array $defects
     * @param $type
     * @return string
     */
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

    /**
     * Prints progress for large test collections
     */
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

    /**
     * Get the footer for a test collection that had tests with
     * failures or errors
     *
     * @return string
     */
    private function getFailedFooter()
    {
        $formatString = "FAILURES!\nTests: %d, Assertions: %d, Failures: %d, Errors: %d.\n";

        return "\n" . $this->red(
            sprintf($formatString,
                       $this->results->getTotalTests(),
                       $this->results->getTotalAssertions(),
                       $this->results->getTotalFailures(),
                       $this->results->getTotalErrors())
        );
    }

    /**
     * Get the footer for a test collection containing all successful
     * tests
     *
     * @return string
     */
    private function getSuccessFooter()
    {
        $tests = $this->results->getTotalTests();
        $asserts = $this->results->getTotalAssertions();

        return $this->green(
            sprintf("OK (%d test%s, %d assertion%s)\n",
                       $tests,
                       ($tests == 1) ? '' : 's',
                       $asserts,
                       ($asserts == 1) ? '' : 's')
        );
    }

    private function green($text)
    {
        if ($this->colors) {
            return "\x1b[30;42m\x1b[2K"
                . $text
                . "\x1b[0m\x1b[2K";
        }
        return $text;
    }

    private function red($text)
    {
        if ($this->colors) {
            return "\x1b[37;41m\x1b[2K"
                . $text
                . "\x1b[0m\x1b[2K";
        }
        return $text;
    }

    /**
     * Deletes all the log files for ExecutableTest objects
     * being printed
     */
    private function clearLogs()
    {
        //remove temporary logs
        foreach($this->suites as $suite)
            $suite->deleteFile();
    }
}
