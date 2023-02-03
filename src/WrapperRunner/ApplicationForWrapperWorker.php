<?php

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Event\TestSuite\TestSuite as EventTestSuite;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Logging\JUnit\JunitXmlLogger;
use PHPUnit\Logging\TeamCity\TeamCityLogger;
use PHPUnit\Logging\TestDox\TestResultCollector;
use PHPUnit\Runner\CodeCoverage;
use PHPUnit\Runner\TestSuiteLoader;
use PHPUnit\Runner\TestSuiteSorter;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use PHPUnit\TextUI\Configuration\Builder;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\TextUI\Configuration\PhpHandler;
use PHPUnit\TextUI\Output\Default\ProgressPrinter\ProgressPrinter;
use PHPUnit\TextUI\Output\DefaultPrinter;
use PHPUnit\TextUI\Output\NullPrinter;
use PHPUnit\TextUI\Output\TestDox\ResultPrinter as TestDoxResultPrinter;
use PHPUnit\TextUI\TestSuiteFilterProcessor;
use PHPUnit\Util\ExcludeList;

/** @internal */
final class ApplicationForWrapperWorker
{
    private bool $hasBeenBootstrapped = false;
    private Configuration $configuration;
    private TestResultCollector $testdoxResultCollector;

    public function __construct(
        private readonly array  $argv,
        private readonly string $progressFile,
        private readonly string $testresultFile,
        private readonly ?string $teamcityFile,
        private readonly ?string $testdoxFile,
        private readonly bool $testdoxColor,
    )
    {}

    public function runTest(string $testPath): int
    {
        $this->bootstrap();

        $testSuiteRefl = (new TestSuiteLoader())->load($testPath);
        $testSuite = TestSuite::fromClassReflector($testSuiteRefl);

        (new TestSuiteFilterProcessor)->process($this->configuration, $testSuite);

        EventFacade::emitter()->testRunnerExecutionStarted(
            EventTestSuite::fromTestSuite($testSuite)
        );

        $testSuite->run();
        
        return TestResultFacade::result()->wasSuccessfulIgnoringPhpunitWarnings()
            ? RunnerInterface::SUCCESS_EXIT
            : RunnerInterface::FAILURE_EXIT
        ;
    }

    private function bootstrap(): void
    {
        if ($this->hasBeenBootstrapped) {
            return;
        }

        ExcludeList::addDirectory(__DIR__);
        EventFacade::emitter()->applicationStarted();

        $this->configuration = (new Builder())->build($this->argv);

        (new PhpHandler)->handle($this->configuration->php());

        if ($this->configuration->hasBootstrap()) {
            $bootstrapFilename = $this->configuration->bootstrap();
            include_once $bootstrapFilename;
            EventFacade::emitter()->testRunnerBootstrapFinished($bootstrapFilename);
        }

        CodeCoverage::init($this->configuration);

        if ($this->configuration->hasLogfileJunit()) {
            new JunitXmlLogger(DefaultPrinter::from($this->configuration->logfileJunit()));
        }

        new ProgressPrinter(
            DefaultPrinter::from($this->progressFile),
            false,
            120
        );

        if (isset($this->teamcityFile)) {
            new TeamCityLogger(DefaultPrinter::from($this->teamcityFile));
        }

        if (isset($this->testdoxFile)) {
            $this->testdoxResultCollector = new TestResultCollector();
        }

        TestResultFacade::init();
        EventFacade::seal();
        EventFacade::emitter()->testRunnerStarted();

        if ($this->configuration->executionOrder() === TestSuiteSorter::ORDER_RANDOMIZED) {
            mt_srand($this->configuration->randomOrderSeed());
        }

        $this->hasBeenBootstrapped = true;
    }

    public function end(): void
    {
        EventFacade::emitter()->testRunnerExecutionFinished();
        EventFacade::emitter()->testRunnerFinished();

        CodeCoverage::generateReports(new NullPrinter, $this->configuration);

        if (isset($this->testdoxResultCollector)) {
            (new TestDoxResultPrinter(DefaultPrinter::from($this->testdoxFile), $this->testdoxColor))->print(
                $this->testdoxResultCollector->testMethodsGroupedByClass(),
            );
        }
        
        $result = TestResultFacade::result();
        file_put_contents($this->testresultFile, serialize($result));

        EventFacade::emitter()->applicationFinished(0);
    }
}
