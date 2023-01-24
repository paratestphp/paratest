<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Logging\JUnit\JunitXmlLogger;
use PHPUnit\Logging\TeamCity\TeamCityLogger;
use PHPUnit\Logging\TestDox\HtmlRenderer as TestDoxHtmlRenderer;
use PHPUnit\Logging\TestDox\PlainTextRenderer as TestDoxTextRenderer;
use PHPUnit\Runner\CodeCoverage;
use PHPUnit\Runner\Extension\PharLoader;
use PHPUnit\Runner\ResultCache\NullResultCache;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use PHPUnit\TextUI\CliArguments\Builder as CliBuilder;
use PHPUnit\TextUI\CliArguments\XmlConfigurationFileFinder;
use PHPUnit\TextUI\Configuration\Builder;
use PHPUnit\TextUI\Configuration\Merger;
use PHPUnit\TextUI\Configuration\PhpHandler;
use PHPUnit\TextUI\Configuration\TestSuiteBuilder;
use PHPUnit\TextUI\Output\DefaultPrinter;
use PHPUnit\TextUI\Output\Facade as OutputFacade;
use PHPUnit\TextUI\ShellExitCodeCalculator;
use PHPUnit\TextUI\TestRunner;
use PHPUnit\TextUI\XmlConfiguration\DefaultConfiguration;
use PHPUnit\TextUI\XmlConfiguration\Loader;

/** @internal */
final class ApplicationForWrapperWorker
{
    private bool $hasBeenBootstrapped = false;

    public function runTest(array $argv): void
    {
        if (! $this->hasBeenBootstrapped) {
            $this->bootstrap($argv);
        }

        $cliConfiguration           = (new CliBuilder)->fromParameters($argv);
        $pathToXmlConfigurationFile = (new XmlConfigurationFileFinder)->find($cliConfiguration);
        $xmlConfiguration = false !== $pathToXmlConfigurationFile
            ? (new Loader)->load($pathToXmlConfigurationFile)
            : DefaultConfiguration::create()
        ;

        $configuration = (new Merger)->merge(
            $cliConfiguration,
            $xmlConfiguration
        );

        CodeCoverage::init($configuration);

        new JunitXmlLogger(
            OutputFacade::printerFor($configuration->logfileJunit()),
        );

        if ($configuration->hasLogfileTeamcity()) {
            new TeamCityLogger(
                DefaultPrinter::from(
                    $configuration->logfileTeamcity()
                )
            );
        }
        
        $testSuite = (new TestSuiteBuilder)->build($configuration);

        (new TestRunner)->run(
            $configuration,
            new NullResultCache,
            $testSuite
        );
    }
    
    private function bootstrap(array $argv): void
    {
        EventFacade::emitter()->applicationStarted();

        $configuration = (new Builder())->build($argv);

        (new PhpHandler)->handle($configuration->php());

        if ($configuration->hasBootstrap()) {
            $bootstrapFilename = $configuration->bootstrap();
            include_once $bootstrapFilename;
            EventFacade::emitter()->testRunnerBootstrapFinished($bootstrapFilename);
        }
        
        $this->hasBeenBootstrapped = true;
    }
}
