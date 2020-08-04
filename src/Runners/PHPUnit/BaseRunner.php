<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Logging\JUnit\Writer;
use ParaTest\Logging\LogInterpreter;
use ParaTest\Parser\ParsedFunction;
use Symfony\Component\Console\Output\OutputInterface;

use function array_merge;
use function getenv;
use function putenv;
use function sprintf;

abstract class BaseRunner implements RunnerInterface
{
    /** @var Options */
    protected $options;

    /** @var LogInterpreter */
    protected $interpreter;

    /** @var ResultPrinter */
    protected $printer;

    /**
     * A collection of pending ExecutableTest objects that have
     * yet to run.
     *
     * @var array<int|string, ExecutableTest|TestMethod|ParsedFunction>
     */
    protected $pending = [];

    /**
     * A collection of ExecutableTest objects that have processes
     * currently running.
     *
     * @var array|ExecutableTest[]
     */
    protected $running = [];

    /**
     * A tallied exit code that returns the highest exit
     * code returned out of the entire collection of tests.
     *
     * @var int
     */
    protected $exitcode = -1;

    /**
     * CoverageMerger to hold track of the accumulated coverage.
     *
     * @var CoverageMerger
     */
    protected $coverage = null;

    /** @var OutputInterface */
    protected $output;

    public function __construct(Options $opts, OutputInterface $output)
    {
        $this->options     = $opts;
        $this->interpreter = new LogInterpreter();
        $this->printer     = new ResultPrinter($this->interpreter, $output);
        $this->output      = $output;
    }

    /**
     * Builds the collection of pending ExecutableTest objects
     * to run. If functional mode is enabled $this->pending will
     * contain a collection of TestMethod objects instead of Suite
     * objects.
     */
    private function load(SuiteLoader $loader): void
    {
        $this->beforeLoadChecks();
        $loader->load($this->options->path);
        $executables   = $this->options->functional ? $loader->getTestMethods() : $loader->getSuites();
        $this->pending = array_merge($this->pending, $executables);
        foreach ($this->pending as $pending) {
            $this->printer->addTest($pending);
        }
    }

    abstract protected function beforeLoadChecks(): void;

    /**
     * Returns the highest exit code encountered
     * throughout the course of test execution.
     */
    final public function getExitCode(): int
    {
        return $this->exitcode;
    }

    /**
     * Write output to JUnit format if requested.
     */
    final protected function log(): void
    {
        if (! isset($this->options->filtered['log-junit'])) {
            return;
        }

        $output = $this->options->filtered['log-junit'];
        $writer = new Writer($this->interpreter, $this->options->path);
        $writer->write($output);
    }

    /**
     * Write coverage to file if requested.
     */
    final protected function logCoverage(): void
    {
        if (! $this->hasCoverage()) {
            return;
        }

        $filteredOptions = $this->options->filtered;

        $reporter = $this->getCoverage()->getReporter();

        if (isset($filteredOptions['coverage-clover'])) {
            $reporter->clover($filteredOptions['coverage-clover']);
        }

        if (isset($filteredOptions['coverage-crap4j'])) {
            $reporter->crap4j($filteredOptions['coverage-crap4j']);
        }

        if (isset($filteredOptions['coverage-html'])) {
            $reporter->html($filteredOptions['coverage-html']);
        }

        if (isset($filteredOptions['coverage-text'])) {
            $this->output->write($reporter->text());
        }

        if (isset($filteredOptions['coverage-xml'])) {
            $reporter->xml($filteredOptions['coverage-xml']);
        }

        $reporter->php($filteredOptions['coverage-php']);
    }

    private function initCoverage(): void
    {
        if (! isset($this->options->filtered['coverage-php'])) {
            return;
        }

        $this->coverage = new CoverageMerger($this->options->coverageTestLimit);
    }

    final protected function hasCoverage(): bool
    {
        return $this->getCoverage() !== null;
    }

    final protected function getCoverage(): ?CoverageMerger
    {
        return $this->coverage;
    }

    /**
     * Overrides envirenment variables if needed.
     */
    private function overrideEnvironmentVariables(): void
    {
        if (! isset($this->options->filtered['configuration'])) {
            return;
        }

        $variables = $this->options->filtered['configuration']->getEnvironmentVariables();

        foreach ($variables as $key => $value) {
            $localEnvValue = getenv($key, true);
            if ($localEnvValue === false) {
                $localEnvValue = $value;
            }

            putenv(sprintf('%s=%s', $key, $localEnvValue));

            $_ENV[$key] = $localEnvValue;
        }
    }

    final protected function initialize(): void
    {
        $this->overrideEnvironmentVariables();
        $this->initCoverage();
        $this->load(new SuiteLoader($this->options));
        $this->printer->start($this->options);
    }
}
