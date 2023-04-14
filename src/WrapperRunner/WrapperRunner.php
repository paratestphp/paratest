<?php

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\JUnit\LogMerger;
use ParaTest\JUnit\Writer;
use ParaTest\Options;
use ParaTest\RunnerInterface;
use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Runner\CodeCoverage;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Configuration\CodeCoverageFilterRegistry;
use PHPUnit\TextUI\ShellExitCodeCalculator;
use PHPUnit\Util\ExcludeList;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;

use function array_merge;
use function array_merge_recursive;
use function array_shift;
use function assert;
use function count;
use function dirname;
use function file_get_contents;
use function max;
use function realpath;
use function unlink;
use function unserialize;
use function usleep;

use const DIRECTORY_SEPARATOR;

/** @internal */
final class WrapperRunner implements RunnerInterface
{
    private const CYCLE_SLEEP = 10000;
    private readonly ResultPrinter $printer;

    /** @var list<non-empty-string> */
    private array $pending = [];
    private int $exitcode  = -1;
    /** @var array<positive-int,WrapperWorker> */
    private array $workers = [];
    /** @var array<int,int> */
    private array $batches = [];

    /** @var list<SplFileInfo> */
    private array $statusFiles = [];
    /** @var list<SplFileInfo> */
    private array $progressFiles = [];
    /** @var list<SplFileInfo> */
    private array $testresultFiles = [];
    /** @var list<SplFileInfo> */
    private array $coverageFiles = [];
    /** @var list<SplFileInfo> */
    private array $junitFiles = [];
    /** @var list<SplFileInfo> */
    private array $teamcityFiles = [];
    /** @var list<SplFileInfo> */
    private array $testdoxFiles = [];
    /** @var non-empty-string[] */
    private readonly array $parameters;
    private CodeCoverageFilterRegistry $codeCoverageFilterRegistry;

    public function __construct(
        private readonly Options $options,
        private readonly OutputInterface $output
    ) {
        $this->printer = new ResultPrinter($output, $options);

        $wrapper = realpath(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit-wrapper.php',
        );
        assert($wrapper !== false);
        $phpFinder = new PhpExecutableFinder();
        $phpBin    = $phpFinder->find(false);
        assert($phpBin !== false);
        $parameters = [$phpBin];
        $parameters = array_merge($parameters, $phpFinder->findArguments());

        if ($options->passthruPhp !== null) {
            $parameters = array_merge($parameters, $options->passthruPhp);
        }

        $parameters[] = $wrapper;

        $this->parameters                 = $parameters;
        $this->codeCoverageFilterRegistry = new CodeCoverageFilterRegistry();
    }

    public function run(): int
    {
        $directory = dirname(__DIR__);
        assert($directory !== '');
        ExcludeList::addDirectory($directory);
        TestResultFacade::init();
        EventFacade::instance()->seal();
        $suiteLoader = new SuiteLoader(
            $this->options,
            $this->output,
            $this->codeCoverageFilterRegistry,
        );
        $result      = TestResultFacade::result();

        $this->pending = $suiteLoader->files;
        $this->printer->setTestCount($suiteLoader->testCount);
        $this->printer->start();
        $this->startWorkers();
        $this->assignAllPendingTests();
        $this->waitForAllToFinish();

        return $this->complete($result);
    }

    private function startWorkers(): void
    {
        for ($token = 1; $token <= $this->options->processes; ++$token) {
            $this->startWorker($token);
        }
    }

    private function assignAllPendingTests(): void
    {
        $batchSize = $this->options->maxBatchSize;

        while (count($this->pending) > 0 && count($this->workers) > 0) {
            foreach ($this->workers as $token => $worker) {
                if (! $worker->isRunning()) {
                    throw $worker->getWorkerCrashedException();
                }

                if (! $worker->isFree()) {
                    continue;
                }

                $this->flushWorker($worker);

                if ($batchSize !== 0 && $this->batches[$token] === $batchSize) {
                    $this->destroyWorker($token);
                    $worker = $this->startWorker($token);
                }

                if (
                    $this->exitcode > 0
                    && $this->options->configuration->stopOnFailure()
                ) {
                    $this->pending = [];
                } elseif (($pending = array_shift($this->pending)) !== null) {
                    $worker->assign($pending);
                    $this->batches[$token]++;
                }
            }

            usleep(self::CYCLE_SLEEP);
        }
    }

    private function flushWorker(WrapperWorker $worker): void
    {
        $this->exitcode = max($this->exitcode, $worker->getExitCode());
        $this->printer->printFeedback(
            $worker->progressFile,
            $this->teamcityFiles,
        );
        $worker->reset();
    }

    private function waitForAllToFinish(): void
    {
        $stopped = [];
        while (count($this->workers) > 0) {
            foreach ($this->workers as $index => $worker) {
                if ($worker->isRunning()) {
                    if (! isset($stopped[$index]) && $worker->isFree()) {
                        $worker->stop();
                        $stopped[$index] = true;
                    }

                    continue;
                }

                if (! $worker->isFree()) {
                    throw $worker->getWorkerCrashedException();
                }

                $this->flushWorker($worker);
                unset($this->workers[$index]);
            }

            usleep(self::CYCLE_SLEEP);
        }
    }

    /** @param positive-int $token */
    private function startWorker(int $token): WrapperWorker
    {
        $worker = new WrapperWorker(
            $this->output,
            $this->options,
            $this->parameters,
            $token,
        );
        $worker->start();
        $this->batches[$token] = 0;

        $this->statusFiles[]     = $worker->statusFile;
        $this->progressFiles[]   = $worker->progressFile;
        $this->testresultFiles[] = $worker->testresultFile;

        if (isset($worker->junitFile)) {
            $this->junitFiles[] = $worker->junitFile;
        }

        if (isset($worker->coverageFile)) {
            $this->coverageFiles[] = $worker->coverageFile;
        }

        if (isset($worker->teamcityFile)) {
            $this->teamcityFiles[] = $worker->teamcityFile;
        }

        if (isset($worker->testdoxFile)) {
            $this->testdoxFiles[] = $worker->testdoxFile;
        }

        return $this->workers[$token] = $worker;
    }

    private function destroyWorker(int $token): void
    {
        // Mutation Testing tells us that the following `unset()` already destroys
        // the `WrapperWorker`, which destroys the Symfony's `Process`, which
        // automatically calls `Process::stop` within `Process::__destruct()`.
        // But we prefer to have an explicit stops.
        $this->workers[$token]->stop();

        unset($this->workers[$token]);
    }

    private function complete(TestResult $testResultSum): int
    {
        foreach ($this->testresultFiles as $testresultFile) {
            if (! $testresultFile->isFile()) {
                continue;
            }

            $contents = file_get_contents($testresultFile->getPathname());
            assert($contents !== false);
            $testResult = unserialize($contents);
            assert($testResult instanceof TestResult);

            $testResultSum = new TestResult(
                $testResultSum->numberOfTests() + $testResult->numberOfTests(),
                $testResultSum->numberOfTestsRun() + $testResult->numberOfTestsRun(),
                $testResultSum->numberOfAssertions() + $testResult->numberOfAssertions(),
                array_merge_recursive($testResultSum->testErroredEvents(), $testResult->testErroredEvents()),
                array_merge_recursive($testResultSum->testFailedEvents(), $testResult->testFailedEvents()),
                array_merge_recursive($testResultSum->testConsideredRiskyEvents(), $testResult->testConsideredRiskyEvents()),
                array_merge_recursive($testResultSum->testSuiteSkippedEvents(), $testResult->testSuiteSkippedEvents()),
                array_merge_recursive($testResultSum->testSkippedEvents(), $testResult->testSkippedEvents()),
                array_merge_recursive($testResultSum->testMarkedIncompleteEvents(), $testResult->testMarkedIncompleteEvents()),
                array_merge_recursive($testResultSum->testTriggeredDeprecationEvents(), $testResult->testTriggeredDeprecationEvents()),
                array_merge_recursive($testResultSum->testTriggeredPhpDeprecationEvents(), $testResult->testTriggeredPhpDeprecationEvents()),
                array_merge_recursive($testResultSum->testTriggeredPhpunitDeprecationEvents(), $testResult->testTriggeredPhpunitDeprecationEvents()),
                array_merge_recursive($testResultSum->testTriggeredErrorEvents(), $testResult->testTriggeredErrorEvents()),
                array_merge_recursive($testResultSum->testTriggeredNoticeEvents(), $testResult->testTriggeredNoticeEvents()),
                array_merge_recursive($testResultSum->testTriggeredPhpNoticeEvents(), $testResult->testTriggeredPhpNoticeEvents()),
                array_merge_recursive($testResultSum->testTriggeredWarningEvents(), $testResult->testTriggeredWarningEvents()),
                array_merge_recursive($testResultSum->testTriggeredPhpWarningEvents(), $testResult->testTriggeredPhpWarningEvents()),
                array_merge_recursive($testResultSum->testTriggeredPhpunitErrorEvents(), $testResult->testTriggeredPhpunitErrorEvents()),
                array_merge_recursive($testResultSum->testTriggeredPhpunitWarningEvents(), $testResult->testTriggeredPhpunitWarningEvents()),
                array_merge_recursive($testResultSum->testRunnerTriggeredDeprecationEvents(), $testResult->testRunnerTriggeredDeprecationEvents()),
                array_merge_recursive($testResultSum->testRunnerTriggeredWarningEvents(), $testResult->testRunnerTriggeredWarningEvents()),
            );
        }

        $this->printer->printResults(
            $testResultSum,
            $this->teamcityFiles,
            $this->testdoxFiles,
        );
        $this->generateCodeCoverageReports();
        $this->generateLogs();

        $exitcode = (new ShellExitCodeCalculator())->calculate(
            $this->options->configuration->failOnDeprecation(),
            $this->options->configuration->failOnEmptyTestSuite(),
            $this->options->configuration->failOnIncomplete(),
            $this->options->configuration->failOnNotice(),
            $this->options->configuration->failOnRisky(),
            $this->options->configuration->failOnSkipped(),
            $this->options->configuration->failOnWarning(),
            $testResultSum,
        );

        $this->clearFiles($this->statusFiles);
        $this->clearFiles($this->progressFiles);
        $this->clearFiles($this->testresultFiles);
        $this->clearFiles($this->coverageFiles);
        $this->clearFiles($this->junitFiles);
        $this->clearFiles($this->teamcityFiles);
        $this->clearFiles($this->testdoxFiles);

        return $exitcode;
    }

    protected function generateCodeCoverageReports(): void
    {
        if ($this->coverageFiles === []) {
            return;
        }

        $coverageManager = new CodeCoverage();
        $coverageManager->init(
            $this->options->configuration,
            $this->codeCoverageFilterRegistry,
            false,
        );
        $coverageMerger = new CoverageMerger($coverageManager->codeCoverage());
        foreach ($this->coverageFiles as $coverageFile) {
            $coverageMerger->addCoverageFromFile($coverageFile);
        }

        $coverageManager->generateReports(
            $this->printer->printer,
            $this->options->configuration,
        );
    }

    private function generateLogs(): void
    {
        if ($this->junitFiles === []) {
            return;
        }

        $testSuite = (new LogMerger())->merge($this->junitFiles);
        (new Writer())->write(
            $testSuite,
            $this->options->configuration->logfileJunit(),
        );
    }

    /** @param list<SplFileInfo> $files */
    private function clearFiles(array $files): void
    {
        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            unlink($file->getPathname());
        }
    }
}
