<?php

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Logging\LogInterpreter;
use ParaTest\Logging\JUnit\Writer;

abstract class BaseRunner
{
    const PHPUNIT_FATAL_ERROR = 255;

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
     * A tallied exit code that returns the highest exit
     * code returned out of the entire collection of tests
     *
     * @var int
     */
    protected $exitcode = -1;

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
    }

    public function run()
    {
        $this->verifyConfiguration();
        $this->initCoverage();
        $this->load();
        $this->printer->start($this->options);
    }

    /**
     * Ensures a valid configuration was supplied. If not
     * causes ParaTest to print the error message and exit immediately
     * with an exit code of 1
     */
    protected function verifyConfiguration()
    {
        if (isset($this->options->filtered['configuration']) && !file_exists($this->options->filtered['configuration']->getPath())) {
            $this->printer->println(sprintf('Could not read "%s".', $this->options->filtered['configuration']));
            exit(1);
        }
    }

    /**
     * Builds the collection of pending ExecutableTest objects
     * to run. If functional mode is enabled $this->pending will
     * contain a collection of TestMethod objects instead of Suite
     * objects
     */
    protected function load()
    {
        $loader = new SuiteLoader($this->options);
        $loader->load($this->options->path);
        $executables = $this->options->functional ? $loader->getTestMethods() : $loader->getSuites();
        $this->pending = array_merge($this->pending, $executables);
        foreach ($this->pending as $pending) {
            $this->printer->addTest($pending);
        }
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
     * Write output to JUnit format if requested
     */
    protected function log()
    {
        if (!isset($this->options->filtered['log-junit'])) {
            return;
        }
        $output = $this->options->filtered['log-junit'];
        $writer = new Writer($this->interpreter, $this->options->path);
        $writer->write($output);
    }

    /**
     * Write coverage to file if requested
     */
    protected function logCoverage()
    {
        if (!$this->hasCoverage()) {
            return;
        }

        $filteredOptions = $this->options->filtered;

        $reporter = $this->getCoverage()->getReporter();

        if (isset($filteredOptions['coverage-clover'])) {
            $reporter->clover($filteredOptions['coverage-clover']);
        }

        if (isset($filteredOptions['coverage-html'])) {
            $reporter->html($filteredOptions['coverage-html']);
        }

        $reporter->php($filteredOptions['coverage-php']);
    }

    protected function initCoverage()
    {
        if (!isset($this->options->filtered['coverage-php'])) {
            return;
        }

        $this->coverage = new CoverageMerger();
    }

    /**
     * @return bool
     */
    protected function hasCoverage()
    {
        return $this->getCoverage() !== null;
    }

    /**
     * @return CoverageMerger
     */
    protected function getCoverage()
    {
        return $this->coverage;
    }

}
