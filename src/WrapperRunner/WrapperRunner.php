<?php

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\JUnit\LogMerger;
use ParaTest\JUnit\Writer;
use ParaTest\Options;
use ParaTest\RunnerInterface;
use ParaTest\TestDox\TestDoxResultsMerger;
use PHPUnit\Logging\TestDox\HtmlRenderer as TestDoxHtmlRenderer;
use PHPUnit\Logging\TestDox\PlainTextRenderer as TestDoxPlainTextRenderer;
use PHPUnit\Logging\TestDox\TestResultCollection as TestDoxTestResultCollection;
use PHPUnit\Runner\CodeCoverage;
use PHPUnit\Runner\ResultCache\DefaultResultCache;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Configuration\CodeCoverageFilterRegistry;
use PHPUnit\TextUI\Output\DefaultPrinter;
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
    private array $unexpectedOutputFiles = [];
    /** @var list<SplFileInfo> */
    private array $resultCacheFiles = [];
    /** @var list<SplFileInfo> */
    private array $testResultFiles = [];
    /** @var list<SplFileInfo> */
    private array $coverageFiles = [];
    /** @var list<SplFileInfo> */
    private array $junitFiles = [];
    /** @var list<SplFileInfo> */
    private array $teamcityFiles = [];
    /** @var list<SplFileInfo> */
    private array $testdoxFiles = [];
    /** @var array<non-empty-string> */
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
        assert($phpBin !== '');
        $parameters = [$phpBin];
        /** @var array<non-empty-string> $arguments */
        $arguments  = $phpFinder->findArguments();
        $parameters = array_merge($parameters, $arguments);

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
        $suiteLoader = new SuiteLoader(
            $this->options,
            $this->output,
            $this->codeCoverageFilterRegistry,
        );
        $result      = TestResultFacade::result();

        $this->pending = $suiteLoader->tests;
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
            $worker->unexpectedOutputFile,
            $worker->teamcityFile ?? null,
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

        $this->statusFiles[]           = $worker->statusFile;
        $this->progressFiles[]         = $worker->progressFile;
        $this->unexpectedOutputFiles[] = $worker->unexpectedOutputFile;
        $this->testResultFiles[]       = $worker->testResultFile;

        if (isset($worker->resultCacheFile)) {
            $this->resultCacheFiles[] = $worker->resultCacheFile;
        }

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
        $this->workers[$token]->stop();
        // We need to wait for ApplicationForWrapperWorker::end to end
        while ($this->workers[$token]->isRunning()) {
            usleep(self::CYCLE_SLEEP);
        }

        unset($this->workers[$token]);
    }

    private function complete(TestResult $testResultSum): int
    {
        foreach ($this->testResultFiles as $testresultFile) {
            if (! $testresultFile->isFile()) {
                continue;
            }

            $contents = file_get_contents($testresultFile->getPathname());
            assert($contents !== false);
            $testResult = unserialize($contents);
            assert($testResult instanceof TestResult);

            $testResultSum = new TestResult(
                (int) $testResultSum->hasTests() + (int) $testResult->hasTests(),
                $testResultSum->numberOfTestsRun() + $testResult->numberOfTestsRun(),
                $testResultSum->numberOfAssertions() + $testResult->numberOfAssertions(),
                array_merge_recursive($testResultSum->testErroredEvents(), $testResult->testErroredEvents()),
                array_merge_recursive($testResultSum->testFailedEvents(), $testResult->testFailedEvents()),
                array_merge_recursive($testResultSum->testConsideredRiskyEvents(), $testResult->testConsideredRiskyEvents()),
                array_merge_recursive($testResultSum->testSuiteSkippedEvents(), $testResult->testSuiteSkippedEvents()),
                array_merge_recursive($testResultSum->testSkippedEvents(), $testResult->testSkippedEvents()),
                array_merge_recursive($testResultSum->testMarkedIncompleteEvents(), $testResult->testMarkedIncompleteEvents()),
                array_merge_recursive($testResultSum->testTriggeredPhpunitDeprecationEvents(), $testResult->testTriggeredPhpunitDeprecationEvents()),
                array_merge_recursive($testResultSum->testTriggeredPhpunitErrorEvents(), $testResult->testTriggeredPhpunitErrorEvents()),
                array_merge_recursive($testResultSum->testTriggeredPhpunitNoticeEvents(), $testResult->testTriggeredPhpunitNoticeEvents()),
                array_merge_recursive($testResultSum->testTriggeredPhpunitWarningEvents(), $testResult->testTriggeredPhpunitWarningEvents()),
                array_merge_recursive($testResultSum->testRunnerTriggeredDeprecationEvents(), $testResult->testRunnerTriggeredDeprecationEvents()),
                array_merge_recursive($testResultSum->testRunnerTriggeredNoticeEvents(), $testResult->testRunnerTriggeredNoticeEvents()),
                array_merge_recursive($testResultSum->testRunnerTriggeredWarningEvents(), $testResult->testRunnerTriggeredWarningEvents()),
                array_merge_recursive($testResultSum->errors(), $testResult->errors()),
                array_merge_recursive($testResultSum->deprecations(), $testResult->deprecations()),
                array_merge_recursive($testResultSum->notices(), $testResult->notices()),
                array_merge_recursive($testResultSum->warnings(), $testResult->warnings()),
                array_merge_recursive($testResultSum->phpDeprecations(), $testResult->phpDeprecations()),
                array_merge_recursive($testResultSum->phpNotices(), $testResult->phpNotices()),
                array_merge_recursive($testResultSum->phpWarnings(), $testResult->phpWarnings()),
                $testResultSum->numberOfIssuesIgnoredByBaseline() + $testResult->numberOfIssuesIgnoredByBaseline(),
            );
        }

        if ($this->options->configuration->cacheResult()) {
            $resultCacheSum = new DefaultResultCache($this->options->configuration->testResultCacheFile());
            foreach ($this->resultCacheFiles as $resultCacheFile) {
                $resultCache = new DefaultResultCache($resultCacheFile->getPathname());
                $resultCache->load();

                $resultCacheSum->mergeWith($resultCache);
            }

            $resultCacheSum->persist();
        }

        $testdoxResults = (new TestDoxResultsMerger())->getResultsFromTestdoxFiles($this->testdoxFiles);

        $this->printer->printResults(
            $testResultSum,
            $this->teamcityFiles,
            $testdoxResults,
        );
        $this->generateCodeCoverageReports();
        $this->generateJunitLog();
        $this->generateTestDoxLogs($testdoxResults);

        $exitcode = (new ShellExitCodeCalculator())->calculate(
            $this->options->configuration,
            $testResultSum,
        );

        $this->clearFiles($this->statusFiles);
        $this->clearFiles($this->progressFiles);
        $this->clearFiles($this->unexpectedOutputFiles);
        $this->clearFiles($this->testResultFiles);
        $this->clearFiles($this->resultCacheFiles);
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

    private function generateJunitLog(): void
    {
        if ($this->junitFiles === []) {
            return;
        }

        $testSuite = (new LogMerger())->merge($this->junitFiles);
        if ($testSuite === null) {
            return;
        }

        (new Writer())->write(
            $testSuite,
            $this->options->configuration->logfileJunit(),
        );
    }

    /** @param array<string,TestDoxTestResultCollection> $testdoxResults */
    private function generateTestDoxLogs(array $testdoxResults): void
    {
        if ($this->options->configuration->hasLogfileTestdoxText()) {
            $testdoxTextContent = (new TestDoxPlainTextRenderer())->render($testdoxResults);
            DefaultPrinter::from($this->options->configuration->logfileTestdoxText())->print($testdoxTextContent);
        }

        if (! $this->options->configuration->hasLogfileTestdoxHtml()) {
            return;
        }

        $testdoxHtmlContent = (new TestDoxHtmlRenderer())->render($testdoxResults);
        DefaultPrinter::from($this->options->configuration->logfileTestdoxHtml())->print($testdoxHtmlContent);
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
