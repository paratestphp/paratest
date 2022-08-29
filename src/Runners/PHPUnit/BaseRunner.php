<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Coverage\CoverageReporter;
use ParaTest\Logging\JUnit\Writer;
use ParaTest\Logging\LogInterpreter;
use SebastianBergmann\Timer\Timer;
use Symfony\Component\Console\Output\OutputInterface;

use function array_reverse;
use function assert;
use function file_put_contents;
use function mt_srand;
use function shuffle;
use function sprintf;

/** @internal */
abstract class BaseRunner implements RunnerInterface
{
    protected const CYCLE_SLEEP = 10000;

    /** @var Options */
    protected $options;

    /** @var ResultPrinter */
    protected $printer;

    /**
     * A collection of pending ExecutableTest objects that have
     * yet to run.
     *
     * @var ExecutableTest[]
     */
    protected $pending = [];

    /**
     * A tallied exit code that returns the highest exit
     * code returned out of the entire collection of tests.
     *
     * @var int
     */
    protected $exitcode = 0;

    /** @var OutputInterface */
    protected $output;

    /** @var LogInterpreter */
    private $interpreter;

    /**
     * CoverageMerger to hold track of the accumulated coverage.
     *
     * @var CoverageMerger|null
     */
    private $coverage = null;

    public function __construct(Options $options, OutputInterface $output)
    {
        $this->options     = $options;
        $this->output      = $output;
        $this->interpreter = new LogInterpreter();
        $this->printer     = new ResultPrinter($this->interpreter, $output, $options);

        if (! $this->options->hasCoverage()) {
            return;
        }

        $this->coverage = new CoverageMerger($this->options->coverageTestLimit());
    }

    final public function run(): void
    {
        $this->load(new SuiteLoader($this->options, $this->output));
        $this->printer->start();

        $this->doRun();

        $this->complete();
    }

    abstract protected function doRun(): void;

    /**
     * Builds the collection of pending ExecutableTest objects
     * to run. If functional mode is enabled $this->pending will
     * contain a collection of TestMethod objects instead of Suite
     * objects.
     */
    private function load(SuiteLoader $loader): void
    {
        $this->beforeLoadChecks();
        $loader->load();
        $this->pending = $this->options->functional()
            ? $loader->getTestMethods()
            : $loader->getSuites();

        $this->sortPending();

        foreach ($this->pending as $pending) {
            $this->printer->addTest($pending);
        }
    }

    private function sortPending(): void
    {
        if ($this->options->orderBy() === Options::ORDER_RANDOM) {
            mt_srand($this->options->randomOrderSeed());
            shuffle($this->pending);
        }

        if ($this->options->orderBy() !== Options::ORDER_REVERSE) {
            return;
        }

        $this->pending = array_reverse($this->pending);
    }

    abstract protected function beforeLoadChecks(): void;

    /**
     * Finalizes the run process. This method
     * prints all results, rewinds the log interpreter,
     * logs any results to JUnit, and cleans up temporary
     * files.
     */
    private function complete(): void
    {
        $this->printer->printResults();
        $this->log();
        $this->logCoverage();
        $readers = $this->interpreter->getReaders();
        foreach ($readers as $reader) {
            $reader->removeLog();
        }
    }

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
        if (($logJunit = $this->options->logJunit()) === null) {
            return;
        }

        $name = $this->options->path() ?? '';

        $writer = new Writer($this->interpreter, $name);
        $writer->write($logJunit);
    }

    /**
     * Write coverage to file if requested.
     */
    final protected function logCoverage(): void
    {
        if (! $this->hasCoverage()) {
            return;
        }

        $coverageMerger = $this->getCoverage();
        assert($coverageMerger !== null);
        $codeCoverage = $coverageMerger->getCodeCoverageObject();
        assert($codeCoverage !== null);
        $codeCoverageConfiguration = null;
        if (($configuration = $this->options->configuration()) !== null) {
            $codeCoverageConfiguration = $configuration->codeCoverage();
        }

        $reporter = new CoverageReporter($codeCoverage, $codeCoverageConfiguration);

        $output = $this->output;
        $timer  = new Timer();
        $start  = static function (string $format) use ($output, $timer): void {
            $output->write(sprintf("\nGenerating code coverage report in %s format ... ", $format));

            $timer->start();
        };
        $stop   = static function () use ($output, $timer): void {
            $output->write(sprintf("done [%s]\n", $timer->stop()->asString()));
        };

        if (($coverageClover = $this->options->coverageClover()) !== null) {
            $start('Clover XML');
            $reporter->clover($coverageClover);
            $stop();
        }

        if (($coverageCobertura = $this->options->coverageCobertura()) !== null) {
            $start('Cobertura XML');
            $reporter->cobertura($coverageCobertura);
            $stop();
        }

        if (($coverageCrap4j = $this->options->coverageCrap4j()) !== null) {
            $start('Crap4J XML');
            $reporter->crap4j($coverageCrap4j);
            $stop();
        }

        if (($coverageHtml = $this->options->coverageHtml()) !== null) {
            $start('HTML');
            $reporter->html($coverageHtml);
            $stop();
        }

        if (($coveragePhp = $this->options->coveragePhp()) !== null) {
            $start('PHP');
            $reporter->php($coveragePhp);
            $stop();
        }

        if (($coverageText = $this->options->coverageText()) !== null) {
            if ($coverageText === '') {
                $this->output->write($reporter->text($this->options->colors()));
            } else {
                file_put_contents($coverageText, $reporter->text($this->options->colors()));
            }
        }

        if (($coverageXml = $this->options->coverageXml()) === null) {
            return;
        }

        $start('PHPUnit XML');
        $reporter->xml($coverageXml);
        $stop();
    }

    final protected function hasCoverage(): bool
    {
        return $this->options->hasCoverage();
    }

    final protected function getCoverage(): ?CoverageMerger
    {
        return $this->coverage;
    }
}
