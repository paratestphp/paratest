<?php

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

use ParaTest\JUnit\LogMerger;
use ParaTest\JUnit\Writer;
use ParaTest\Options;
use ParaTest\RunnerInterface;
use ParaTest\TestDox\TestDoxResultsMerger;
use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Logging\TestDox\HtmlRenderer as TestDoxHtmlRenderer;
use PHPUnit\Logging\TestDox\PlainTextRenderer as TestDoxPlainTextRenderer;
use PHPUnit\Logging\TestDox\TestResultCollection as TestDoxTestResultCollection;
use PHPUnit\Runner\CodeCoverage;
use PHPUnit\Runner\TestRunHistory\DefaultTestRunHistory;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use PHPUnit\TestRunner\TestResult\Issues\Issue;
use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Configuration\CodeCoverageFilterRegistry;
use PHPUnit\TextUI\Output\DefaultPrinter;
use PHPUnit\TextUI\ShellExitCodeCalculator;
use PHPUnit\Util\ExcludeList;
use ReflectionProperty;
use RuntimeException;
use SebastianBergmann\CodeCoverage\Node\Builder;
use SebastianBergmann\CodeCoverage\Serialization\Merger;
use SebastianBergmann\CodeCoverage\StaticAnalysis\Registry as StaticAnalysisRegistry;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;

use function array_filter;
use function array_map;
use function array_merge;
use function array_merge_recursive;
use function array_shift;
use function array_values;
use function assert;
use function bin2hex;
use function count;
use function dirname;
use function file_get_contents;
use function filemtime;
use function filesize;
use function getenv;
use function implode;
use function is_file;
use function max;
use function mkdir;
use function preg_match;
use function random_bytes;
use function realpath;
use function rmdir;
use function sprintf;
use function str_starts_with;
use function time;
use function unlink;
use function unserialize;
use function usleep;

use const DIRECTORY_SEPARATOR;

/** @internal */
final class WrapperRunner implements RunnerInterface
{
    private const int CYCLE_SLEEP                     = 1000;
    private const int DESYNC_RECOVERY_SECONDS_DEFAULT = 120;
    private const int EXIT_AFTER_STOP_SECONDS_DEFAULT = 180;
    private readonly ResultPrinter $printer;

    /** @var list<non-empty-string> */
    private array $pending = [];
    private int $exitcode  = -1;
    /** @var array<positive-int,WrapperWorker> */
    private array $workers = [];
    /** @var array<int,int> */
    private array $batches = [];
    /** @var array<non-empty-string,true> */
    private array $requiredTestResultFiles = [];
    /** @var array<non-empty-string,true> */
    private array $requiredCoverageFiles = [];

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
    /** @var non-empty-string */
    private string $runTmpDir;

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
        ExcludeList::addDirectory(dirname(__DIR__));
        $suiteLoader = new SuiteLoader(
            $this->options,
            $this->output,
            $this->codeCoverageFilterRegistry,
        );
        $result      = TestResultFacade::result();

        $this->pending = $suiteLoader->tests;
        $this->printer->setTestCount($suiteLoader->testCount);
        $this->printer->start();
        $this->runTmpDir = $this->createRunTmpDir();
        $this->startWorkers();
        $this->assignAllPendingTests();
        $this->waitForAllToFinish();

        return $this->complete($result);
    }

    /**
     * Worker files are deserialized and even included by the main process: they must live in a directory
     * no other user can write into, otherwise a local attacker could plant them in a shared tmp-dir
     *
     * @return non-empty-string
     */
    private function createRunTmpDir(): string
    {
        $runTmpDir = sprintf(
            '%s%sparatest_%s',
            $this->options->tmpDir,
            DIRECTORY_SEPARATOR,
            bin2hex(random_bytes(16)),
        );

        // mkdir fails if the path already exists, symlinks included
        if (! @mkdir($runTmpDir, 0700)) {
            throw new RuntimeException(sprintf('Unable to create private temporary directory "%s"', $runTmpDir));
        }

        return $runTmpDir;
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

        /** @var array<positive-int,int> $desyncSince */
        $desyncSince = [];
        /** @var array<positive-int,true> $recovered */
        $recovered = [];
        /** @var array<positive-int,int> $recoveredSince */
        $recoveredSince = [];

        while (count($this->pending) > 0 && count($this->workers) > 0) {
            $respawn = [];

            foreach ($this->workers as $token => $worker) {
                if (! $worker->isRunning()) {
                    if (! isset($recovered[$token])) {
                        throw $worker->getWorkerCrashedException();
                    }

                    // A worker recovered from the lost-status-byte desync
                    // still reports !isFree() after exiting; its results were
                    // serialized by ApplicationForWrapperWorker::end() before
                    // it died, so flush them and respawn a live worker for
                    // the remaining pending tests.
                    $this->flushWorker($worker);
                    $respawn[] = $token;

                    continue;
                }

                if (! $worker->isFree()) {
                    if (! isset($recovered[$token])) {
                        if (! isset($desyncSince[$token])) {
                            $desyncSince[$token] = time();
                        } elseif ($this->isWorkerDesynced($worker, $desyncSince[$token])) {
                            $this->output->writeln(sprintf(
                                '[paratest] worker %d stalled: status file desync — recovering via controlled exit to preserve results',
                                $token,
                            ));
                            $worker->stop();
                            $recovered[$token]      = true;
                            $recoveredSince[$token] = time();
                        }
                    } elseif (
                        isset($recoveredSince[$token])
                        && $worker->isRunning()
                        && time() - $recoveredSince[$token] > self::exitAfterStopSeconds()
                    ) {
                        $this->output->writeln(sprintf(
                            '[paratest] worker %d stopped but did not exit — killing; serialized results are already on disk',
                            $token,
                        ));
                        $worker->kill();
                    }

                    continue;
                }

                unset($desyncSince[$token]);

                $this->flushWorker($worker);

                if ($batchSize !== 0 && $this->batches[$token] === $batchSize) {
                    $this->destroyWorker($token);
                    $worker = $this->startWorker($token);
                }

                if (
                    $this->exitcode > 0
                    && $this->options->configuration->stopOnFailureThreshold() > 0
                ) {
                    $this->pending = [];
                } elseif (($pending = array_shift($this->pending)) !== null) {
                    $worker->assign($pending);
                    $this->batches[$token]++;
                }
            }

            foreach ($respawn as $token) {
                unset($this->workers[$token], $desyncSince[$token], $recovered[$token], $recoveredSince[$token]);
                $this->startWorker($token);
            }

            usleep(self::CYCLE_SLEEP);
        }

        if ($this->pending === []) {
            return;
        }

        $this->output->writeln(sprintf(
            '[paratest] %d test file(s) left unassigned after worker recovery',
            count($this->pending),
        ));
    }

    private function flushWorker(WrapperWorker $worker): void
    {
        if ($worker->hasExecutedTests()) {
            $testResultFile = $worker->testResultFile->getPathname();
            if ($testResultFile !== '') {
                $this->requiredTestResultFiles[$testResultFile] = true;
            }

            if (isset($worker->coverageFile) && $worker->coverageFile->getPathname() !== '') {
                $this->requiredCoverageFiles[$worker->coverageFile->getPathname()] = true;
            }
        }

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
        /** @var array<positive-int,int> $stoppedSince */
        $stoppedSince = [];
        /** @var array<positive-int,int> $desyncSince */
        $desyncSince = [];
        /** @var array<positive-int,true> $recovered */
        $recovered = [];
        /** @var array<positive-int,true> $killed */
        $killed = [];

        while (count($this->workers) > 0) {
            foreach ($this->workers as $index => $worker) {
                if ($worker->isRunning()) {
                    if (! isset($stopped[$index])) {
                        if ($worker->isFree()) {
                            $worker->stop();
                            $stopped[$index]      = true;
                            $stoppedSince[$index] = time();
                        } elseif (! isset($recovered[$index])) {
                            // A worker whose status byte was lost parks on
                            // fgets(STDIN) while its inExecution count keeps
                            // it un-free forever. Recover only when the
                            // progress file is also stale: a slow batch keeps
                            // appending progress, a parked worker does not.
                            if (! isset($desyncSince[$index])) {
                                $desyncSince[$index] = time();
                            } elseif ($this->isWorkerDesynced($worker, $desyncSince[$index])) {
                                $this->output->writeln(sprintf(
                                    '[paratest] worker %d stalled: status file desync — recovering via controlled exit to preserve results',
                                    $index,
                                ));
                                $worker->stop();
                                $stopped[$index]      = true;
                                $stoppedSince[$index] = time();
                                $recovered[$index]    = true;
                            }
                        }
                    } elseif (time() - $stoppedSince[$index] > self::exitAfterStopSeconds()) {
                        // A stopped worker serialises its results in end()
                        // then exits; a worker still running past the bound
                        // is hung after its result file was written (or in
                        // PHP shutdown). Killing it loses nothing: flushWorker
                        // merges the on-disk testResultFile as usual.
                        $this->output->writeln(sprintf(
                            '[paratest] worker %d stopped but did not exit — killing; serialized results are already on disk',
                            $index,
                        ));
                        $worker->kill();
                        $killed[$index] = true;
                    }

                    continue;
                }

                if (! $worker->isFree() && ! isset($recovered[$index]) && ! isset($killed[$index])) {
                    throw $worker->getWorkerCrashedException();
                }

                $this->flushWorker($worker);
                unset($this->workers[$index]);
            }

            usleep(self::CYCLE_SLEEP);
        }
    }

    /**
     * Double-stale desync check: the worker has reported !isFree() since
     * $desyncSince AND its progress file stopped growing in the same window.
     */
    private function isWorkerDesynced(WrapperWorker $worker, int $desyncSince): bool
    {
        $threshold = self::desyncRecoverySeconds();

        if (time() - $desyncSince <= $threshold) {
            return false;
        }

        $progressPath = $worker->progressFile->getPathname();
        $mtime        = is_file($progressPath) ? filemtime($progressPath) : 0;

        return time() - $mtime > $threshold;
    }

    private static function desyncRecoverySeconds(): int
    {
        $env = getenv('PARATEST_DESYNC_RECOVERY_SECONDS');

        return $env !== false && (int) $env > 0
            ? (int) $env
            : self::DESYNC_RECOVERY_SECONDS_DEFAULT;
    }

    private static function exitAfterStopSeconds(): int
    {
        $env = getenv('PARATEST_EXIT_AFTER_STOP_SECONDS');

        return $env !== false && (int) $env > 0
            ? (int) $env
            : self::EXIT_AFTER_STOP_SECONDS_DEFAULT;
    }

    /** @param positive-int $token */
    private function startWorker(int $token): WrapperWorker
    {
        $worker = new WrapperWorker(
            $this->output,
            $this->options,
            $this->parameters,
            $token,
            $this->runTmpDir,
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
        // Validate test result files for workers that executed tests
        $missingTestResultFiles = [];
        foreach ($this->requiredTestResultFiles as $filePath => $true) {
            if (is_file($filePath)) {
                continue;
            }

            $missingTestResultFiles[] = $filePath;
        }

        if ($missingTestResultFiles !== []) {
            throw MissingResultsException::create($missingTestResultFiles, 'test_result');
        }

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
                array_merge_recursive($testResultSum->testRunnerTriggeredIssueDeprecationEvents(), $testResult->testRunnerTriggeredIssueDeprecationEvents()),
                array_merge_recursive($testResultSum->testRunnerTriggeredIssueErrorEvents(), $testResult->testRunnerTriggeredIssueErrorEvents()),
                array_merge_recursive($testResultSum->testRunnerTriggeredIssueNoticeEvents(), $testResult->testRunnerTriggeredIssueNoticeEvents()),
                array_merge_recursive($testResultSum->testRunnerTriggeredIssuePhpDeprecationEvents(), $testResult->testRunnerTriggeredIssuePhpDeprecationEvents()),
                array_merge_recursive($testResultSum->testRunnerTriggeredIssuePhpNoticeEvents(), $testResult->testRunnerTriggeredIssuePhpNoticeEvents()),
                array_merge_recursive($testResultSum->testRunnerTriggeredIssuePhpWarningEvents(), $testResult->testRunnerTriggeredIssuePhpWarningEvents()),
                array_merge_recursive($testResultSum->testRunnerTriggeredIssueWarningEvents(), $testResult->testRunnerTriggeredIssueWarningEvents()),
                self::mergeIssues($testResultSum->errors(), $testResult->errors()),
                self::mergeIssues($testResultSum->deprecations(), $testResult->deprecations()),
                self::mergeIssues($testResultSum->notices(), $testResult->notices()),
                self::mergeIssues($testResultSum->warnings(), $testResult->warnings()),
                self::mergeIssues($testResultSum->phpDeprecations(), $testResult->phpDeprecations()),
                self::mergeIssues($testResultSum->phpNotices(), $testResult->phpNotices()),
                self::mergeIssues($testResultSum->phpWarnings(), $testResult->phpWarnings()),
                $testResultSum->numberOfIssuesIgnoredByBaseline() + $testResult->numberOfIssuesIgnoredByBaseline(),
                [
                    'self' => $testResultSum->numberOfSelfDeprecations() + $testResult->numberOfSelfDeprecations(),
                    'direct' => $testResultSum->numberOfDirectDeprecations() + $testResult->numberOfDirectDeprecations(),
                    'indirect' => $testResultSum->numberOfIndirectDeprecations() + $testResult->numberOfIndirectDeprecations(),
                    'unknown' => $testResultSum->numberOfDeprecationsWithUnknownTrigger() + $testResult->numberOfDeprecationsWithUnknownTrigger(),
                ],
                array_merge_recursive($testResultSum->retriedTests(), $testResult->retriedTests()),
            );
        }

        if ($this->options->configuration->recordTestRunHistory()) {
            $resultCacheSum = new DefaultTestRunHistory($this->options->configuration->testRunHistoryFile());
            foreach ($this->resultCacheFiles as $resultCacheFile) {
                $resultCache = new DefaultTestRunHistory($resultCacheFile->getPathname());
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
        rmdir($this->runTmpDir);

        return $exitcode;
    }

    /**
     * PHPUnit reports an issue once, with all the tests that triggered it:
     * the same issue triggered in different workers must be merged the same way
     *
     * @param list<Issue> $issues
     * @param list<Issue> $newIssues
     *
     * @return list<Issue>
     */
    private static function mergeIssues(array $issues, array $newIssues): array
    {
        $mergedIssues = [];
        foreach ([...$issues, ...$newIssues] as $issue) {
            $id = implode(':', [$issue->file(), $issue->line(), $issue->description()]);
            if (! isset($mergedIssues[$id])) {
                $mergedIssues[$id] = clone $issue;

                continue;
            }

            foreach ($issue->triggeringTests() as $triggeringTest) {
                for ($count = 0; $count < $triggeringTest['count']; ++$count) {
                    $mergedIssues[$id]->triggeredBy($triggeringTest['test']);
                }
            }
        }

        return array_values($mergedIssues);
    }

    protected function generateCodeCoverageReports(): void
    {
        if ($this->coverageFiles === []) {
            return;
        }

        // Validate coverage files for workers that executed tests
        $missingCoverageFiles = [];
        foreach ($this->requiredCoverageFiles as $filePath => $true) {
            if (is_file($filePath) && filesize($filePath) !== 0) {
                continue;
            }

            $missingCoverageFiles[] = $filePath;
        }

        if ($missingCoverageFiles !== []) {
            throw MissingResultsException::create($missingCoverageFiles, 'coverage');
        }

        $coverageManager = new CodeCoverage(EventFacade::emitter());
        $coverageManager->init(
            $this->options->configuration,
            $this->codeCoverageFilterRegistry,
            false,
        );
        $coverageFiles      = array_map(static function (SplFileInfo $fileInfo): false|string {
            return $fileInfo->getRealPath();
        }, $this->coverageFiles);
        $coverageFiles      = array_filter($coverageFiles, static function (false|string $file): bool {
            return $file !== false;
        });
        $serializedCoverage = (new Merger())->merge($coverageFiles);

        $codeCoverage = $coverageManager->codeCoverage();
        $analyser     = StaticAnalysisRegistry::analyser(
            $codeCoverage->cachesStaticAnalysis() ? $codeCoverage->cacheDirectory() : null,
            ! $this->options->configuration->disableCodeCoverageIgnore(),
            $this->options->configuration->ignoreDeprecatedCodeUnitsFromCodeCoverage(),
        );
        $report       = (new Builder($analyser))->build(
            $serializedCoverage['codeCoverage'],
            $serializedCoverage['testResults'],
            $serializedCoverage['basePath'],
        );
        $codeCoverage->excludeUncoveredFiles();
        $codeCoverageData = clone $serializedCoverage['codeCoverage'];
        if ($serializedCoverage['basePath'] !== '') {
            foreach ($codeCoverageData->coveredFiles() as $file) {
                if (! self::isRelativePath($file)) {
                    continue;
                }

                $codeCoverageData->renameFile($file, $serializedCoverage['basePath'] . DIRECTORY_SEPARATOR . $file);
            }
        }

        $codeCoverage->setData($codeCoverageData);
        $codeCoverage->setTests($serializedCoverage['testResults']);
        (new ReflectionProperty(\SebastianBergmann\CodeCoverage\CodeCoverage::class, 'cachedReport'))->setValue($codeCoverage, $report);

        $coverageManager->generateReports(
            $this->printer->printer,
            $this->options->configuration,
        );
    }

    private static function isRelativePath(string $path): bool
    {
        return ! str_starts_with($path, 'phar://')
            && ! str_starts_with($path, DIRECTORY_SEPARATOR)
            && preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) !== 1;
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

    /** @param array<class-string, TestDoxTestResultCollection> $testdoxResults */
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
