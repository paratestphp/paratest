<?php
namespace ParaTest\Runners\PHPUnit;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Logging\LogInterpreter;
use ParaTest\Logging\JUnit\Writer;
use PHP_CodeCoverage_Report_Clover;
use PHP_CodeCoverage_Report_HTML;
use PHP_CodeCoverage_Report_PHP;
use RuntimeException;

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
        if (isset($filteredOptions['coverage-clover'])) {
            $clover = new PHP_CodeCoverage_Report_Clover();
            $clover->process($this->getCoverage()->getCoverage(), $filteredOptions['coverage-clover']);
        }

        if (isset($filteredOptions['coverage-html'])) {
            $html = new PHP_CodeCoverage_Report_HTML();
            $html->process($this->getCoverage()->getCoverage(), $filteredOptions['coverage-html']);
        }

        $php = new PHP_CodeCoverage_Report_PHP();
        $php->process($this->getCoverage()->getCoverage(), $filteredOptions['coverage-php']);
    }

    protected function initCoverage()
    {
        if (!isset($this->options->filtered['coverage-php'])) {
            return;
        }

        $this->coverage = new CoverageMerger();
    }

    protected function hasCoverage()
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
     * Returns coverage object from file.
     *
     * @param string $coverageFile Coverage file.
     *
     * @return \PHP_CodeCoverage
     */
    protected function getCoverageObject($coverageFile)
    {
        $coverage = file_get_contents($coverageFile);

        if (substr($coverage, 0, 5) === '<?php') {
            return include $coverageFile;
        }
        
        // the PHPUnit 3.x and below
        return unserialize($coverage);
    }

    /**
     * Adds the coverage contained in $coverageFile and deletes the file afterwards
     * @param $coverageFile
     * @throws RuntimeException
     */
    protected function addCoverageFromFile($coverageFile)
    {
        if ($coverageFile === null || !file_exists($coverageFile)) {
            return;
        }

        if (filesize($coverageFile) == 0) {
            throw new RuntimeException("Coverage file $coverageFile is empty. This means a PHPUnit process has crashed.");
        }

        $this->getCoverage()->addCoverage($this->getCoverageObject($coverageFile));
        unlink($coverageFile);
    }
}
