<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Event\TestSuite\TestSuite as EventTestSuite;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Logging\JUnit\JunitXmlLogger;
use PHPUnit\Logging\TeamCity\TeamCityLogger;
use PHPUnit\Logging\TestDox\HtmlRenderer as TestDoxHtmlRenderer;
use PHPUnit\Logging\TestDox\PlainTextRenderer as TestDoxTextRenderer;
use PHPUnit\Runner\CodeCoverage;
use PHPUnit\Runner\Extension\PharLoader;
use PHPUnit\Runner\ResultCache\NullResultCache;
use PHPUnit\Runner\TestSuiteLoader;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use PHPUnit\TextUI\CliArguments\Builder as CliBuilder;
use PHPUnit\TextUI\CliArguments\XmlConfigurationFileFinder;
use PHPUnit\TextUI\Configuration\Builder;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\TextUI\Configuration\Merger;
use PHPUnit\TextUI\Configuration\PhpHandler;
use PHPUnit\TextUI\Configuration\TestSuiteBuilder;
use PHPUnit\TextUI\Output\Default\ProgressPrinter\ProgressPrinter;
use PHPUnit\TextUI\Output\DefaultPrinter;
use PHPUnit\TextUI\Output\Facade as OutputFacade;
use PHPUnit\TextUI\Output\NullPrinter;
use PHPUnit\TextUI\ShellExitCodeCalculator;
use PHPUnit\TextUI\TestRunner;
use PHPUnit\TextUI\TestSuiteFilterProcessor;
use PHPUnit\TextUI\XmlConfiguration\DefaultConfiguration;
use PHPUnit\TextUI\XmlConfiguration\Loader;

/** @internal */
final class ApplicationForWrapperWorker
{
    private bool $hasBeenBootstrapped = false;
    private Configuration $configuration;

    public function __construct(
        private readonly array  $argv,
        private readonly string $progressFile
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

        EventFacade::emitter()->testRunnerExecutionFinished();

        return $this->getExitCode();
    }
    
    public function end(): void
    {
        EventFacade::emitter()->testRunnerFinished();
        CodeCoverage::generateReports(new NullPrinter, $this->configuration);

        EventFacade::emitter()->applicationFinished($this->getExitCode());
    }

    private function bootstrap(): void
    {
        if ($this->hasBeenBootstrapped) {
            return;
        }

        EventFacade::emitter()->applicationStarted();

        $this->configuration = (new Builder())->build($this->argv);

        (new PhpHandler)->handle($this->configuration->php());

        if ($this->configuration->hasBootstrap()) {
            $bootstrapFilename = $this->configuration->bootstrap();
            include_once $bootstrapFilename;
            EventFacade::emitter()->testRunnerBootstrapFinished($bootstrapFilename);
        }

        CodeCoverage::init($this->configuration);

        new JunitXmlLogger(OutputFacade::printerFor($this->configuration->logfileJunit()));

        if ($this->configuration->hasLogfileTeamcity()) {
            new TeamCityLogger(DefaultPrinter::from($this->configuration->logfileTeamcity()));
        }
        
        new ProgressPrinter(
            DefaultPrinter::from($this->progressFile),
            false,
            80
        );

        TestResultFacade::init();
        EventFacade::seal();
        EventFacade::emitter()->testRunnerStarted();

        $this->hasBeenBootstrapped = true;
    }

    private function getExitCode(): int
    {
        return (new ShellExitCodeCalculator)->calculate(
            $this->configuration->failOnEmptyTestSuite(),
            $this->configuration->failOnRisky(),
            $this->configuration->failOnWarning(),
            $this->configuration->failOnIncomplete(),
            $this->configuration->failOnSkipped(),
            TestResultFacade::result()
        );
    }
}
