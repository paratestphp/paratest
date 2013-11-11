<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Logging\LogInterpreter,
    ParaTest\Logging\JUnit\Writer,
    Habitat\Habitat;

class Runner
{
    const PHPUNIT_FATAL_ERROR = 255;

    /**
     * A collection of pending ExecutableTest objects that have
     * yet to run
     *
     * @var array
     */
    protected $pending = array();

    /**
     * A collection of ExecutableTest objects that have processes
     * currently running
     *
     * @var array
     */
    protected $running = array();

    /**
     * @var Options
     */
    protected $options;

    /**
     * @var \ParaTest\Logging\LogInterpreter
     */
    protected $interpreter;

    /**
     * @var ResultPrinter
     */
    protected $printer;

    /**
     * A tallied exit code that returns the highest exit
     * code returned out of the entire collection of tests
     *
     * @var int
     */
    protected $exitcode = -1;

    /**
     * A collection of available tokens based on the number
     * of processes specified in $options
     *
     * @var array
     */
    protected $tokens = array();

    /**
     * CoverageMerger to hold track of the accumulated coverage
     *
     * @var CoverageMerger
     */
    protected $coverage = null;

    public function __construct($opts = array())
    {
        $this->options = new Options($opts);
        $this->interpreter = new LogInterpreter();
        $this->printer = new ResultPrinter($this->interpreter);
        $this->initTokens();
    }

    /**
     * The money maker. Runs all ExecutableTest objects in separate processes.
     */
    public function run()
    {
        $this->verifyConfiguration();
        $this->initCoverage();
        $this->load();
        $this->printer->start($this->options);
        while (count($this->running) || count($this->pending)) {
            foreach($this->running as $key => $test)
                if (!$this->testIsStillRunning($test)) {
                    unset($this->running[$key]);
                    $this->releaseToken($key);
                }
            $this->fillRunQueue();
            usleep(10000);
        }
        $this->complete();
    }

    /**
     * Returns the highest exit code encountered
     * throughout the course of test execution
     *
     * @return int
     */
    public function getExitCode()
    {
        return $this->exitcode;
    }

    /**
     * Ensures a valid configuration was supplied. If not
     * causes ParaTest to print the error message and exit immediately
     * with an exit code of 1
     */
    private function verifyConfiguration()
    {
        if (isset($this->options->filtered['configuration']) && !file_exists($this->options->filtered['configuration']->getPath())) {
            $this->printer->println(sprintf('Could not read "%s".', $this->options->filtered['configuration']));
            exit(1);
        }
    }

    /**
     * Finalizes the run process. This method
     * prints all results, rewinds the log interpreter,
     * logs any results to JUnit, and cleans up temporary
     * files
     */
    private function complete()
    {
        $this->printer->printResults();
        $this->interpreter->rewind();
        $this->log();
        $this->logCoverage();
        $readers = $this->interpreter->getReaders();
        foreach($readers as $reader)
            $reader->removeLog();
    }

    /**
     * Builds the collection of pending ExecutableTest objects
     * to run. If functional mode is enabled $this->pending will
     * contain a collection of TestMethod objects instead of Suite
     * objects
     */
    private function load()
    {
        $loader = new SuiteLoader($this->options);
        $loader->load($this->options->path);
        $executables = ($this->options->functional) ? $loader->getTestMethods() : $loader->getSuites();
        $this->pending = array_merge($this->pending, $executables);
        foreach($this->pending as $pending)
            $this->printer->addTest($pending);
    }

    /**
     * Write output to JUnit format if requested
     */
    private function log()
    {
        if(!isset($this->options->filtered['log-junit'])) return;
        $output = $this->options->filtered['log-junit'];
        $writer = new Writer($this->interpreter, $this->options->path);
        $writer->write($output);
    }

    /**
     * Write coverage to file if requested
     */
    private function logCoverage()
    {
        if (!$this->hasCoverage()) {
            return;
        }

        $filteredOptions = $this->options->filtered;
        if (isset($filteredOptions['coverage-clover'])) {
            $clover = new \PHP_CodeCoverage_Report_Clover();
            $clover->process($this->getCoverage()->getCoverage(), $filteredOptions['coverage-clover']);
        }

        if (isset($filteredOptions['coverage-html'])) {
            $html = new \PHP_CodeCoverage_Report_HTML();
            $html->process($this->getCoverage()->getCoverage(), $filteredOptions['coverage-html']);
        }

        $php = new \PHP_CodeCoverage_Report_PHP();
        $php->process($this->getCoverage()->getCoverage(),  $filteredOptions['coverage-php']);
    }

    /**
     * This method removes ExecutableTest objects from the pending collection
     * and adds them to the running collection. It is also in charge of recycling and
     * acquiring available test tokens for use
     */
    private function fillRunQueue()
    {
        $opts = $this->options;
        while (sizeof($this->pending) && sizeof($this->running) < $opts->processes) {
            $token = $this->getNextAvailableToken();
            if ($token !== false) {
                $this->acquireToken($token);
                $env = array('TEST_TOKEN' => $token) + Habitat::getAll();
                $this->running[$token] = array_shift($this->pending)->run($opts->phpunit, $opts->filtered, $env);
            }
        }
    }

    /**
     * Returns whether or not a test has finished being
     * executed. If it has, this method also halts a test process - optionally
     * throwing an exception if a fatal error has occurred -
     * prints feedback, and updates the overall exit code
     *
     * @param ExecutableTest $test
     * @return bool
     * @throws \Exception
     */
    private function testIsStillRunning($test)
    {
        if(!$test->isDoneRunning()) return true;
        $this->setExitCode($test);
        $test->stop();
        if (static::PHPUNIT_FATAL_ERROR === $test->getExitCode())
            throw new \Exception($test->getStderr());
        $this->printer->printFeedback($test);
        if ($this->hasCoverage()) {
            $this->addCoverage($test);
        }

        return false;
    }

    /**
     * If the provided test object has an exit code
     * higher than the currently set exit code, that exit
     * code will be set as the overall exit code
     *
     * @param ExecutableTest $test
     */
    private function setExitCode(ExecutableTest $test)
    {
        $exit = $test->getExitCode();
        if($exit > $this->exitcode)
            $this->exitcode = $exit;
    }

    /**
     * Initialize the available test tokens based
     * on how many processes ParaTest will be run in
     */
    protected function initTokens()
    {
        $this->tokens = array();
        for ($i=0; $i< $this->options->processes; $i++) {
            $this->tokens[$i] = true;
        }
    }

    /**
     * Gets the next token that is available to be acquired
     * from a finished process
     *
     * @return bool|int
     */
    protected function getNextAvailableToken()
    {
        for ($i=0; $i< count($this->tokens); $i++) {
            if ($this->tokens[$i]) return $i;
        }

        return false;
    }

    /**
     * Flag a token as available for use
     *
     * @param $tokenIdentifier
     */
    protected function releaseToken($tokenIdentifier)
    {
        $this->tokens[$tokenIdentifier] = true;
    }

    /**
     * Flag a token as acquired and not available for use
     *
     * @param $tokenIdentifier
     */
    protected function acquireToken($tokenIdentifier)
    {
        $this->tokens[$tokenIdentifier] = false;
    }

    private function initCoverage()
    {
        if (!isset($this->options->filtered['coverage-php'])) {
            return;
        }

        $this->coverage = new CoverageMerger();
    }

    private function hasCoverage()
    {
        return $this->getCoverage() != null;
    }

    /**
     * @return CoverageMerger
     */
    public function getCoverage()
    {
        return $this->coverage;
    }

    /**
     * @param ExecutableTest $test
     */
    private function addCoverage($test)
    {
        $coverageFile = $test->getCoverageFileName();
        if (!file_exists($coverageFile)) {
            return;
        }

        /** @var \PHP_CodeCoverage $coverage */
        $coverage = unserialize(file_get_contents($coverageFile));
        $this->getCoverage()->addCoverage($coverage);
        unlink($coverageFile);
    }
}
