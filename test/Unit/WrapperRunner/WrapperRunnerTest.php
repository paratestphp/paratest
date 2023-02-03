<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\WrapperRunner;

use InvalidArgumentException;
use ParaTest\Options;
use ParaTest\Tests\TestBase;
use ParaTest\Tests\TmpDirCreator;
use ParaTest\WrapperRunner\RunnerInterface;
use ParaTest\WrapperRunner\WorkerCrashedException;
use PHPUnit\Framework\Assert;
use PHPUnit\Runner\TestSuiteSorter;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use Symfony\Component\Process\Process;
use function array_diff;
use function array_unique;
use function file_get_contents;
use function min;
use function scandir;
use function unlink;
use const FIXTURES;

/**
 * @internal
 *
 * @covers \ParaTest\WrapperRunner\WrapperRunner
 * @covers \ParaTest\WrapperRunner\WrapperWorker
 * @covers \ParaTest\WrapperRunner\WorkerCrashedException
 */
final class WrapperRunnerTest extends TestBase
{
    protected const NUMBER_OF_CLASS_TESTS_FOR_BATCH_SIZE = 4;
    protected const UNPROCESSABLE_FILENAMES              =  ['..', '.', '.gitignore'];

    public const PASSTHRU_PHP_CUSTOM = 'PASSTHRU_PHP_CUSTOM';

    /** @dataProvider provideForWrapperRunnerHandlesBatchSize */
    public function testWrapperRunnerHandlesBatchSize(int $processes, ?int $batchSize, int $expectedPidCount): void
    {
        $this->bareOptions['--no-configuration'] = true;
        $this->bareOptions['--processes']     = (string) $processes;
        $this->bareOptions['path']            = $this->fixture('wrapper_batchsize_suite');
        if ($batchSize !== null) {
            $this->bareOptions['--max-batch-size'] = (string) $batchSize;
        }

        $tmpDir        = FIXTURES . DS . 'wrapper_batchsize_suite' . DS . 'tmp';
        $pidFilesDir   = $tmpDir . DS . 'pid';
        $tokenFilesDir = $tmpDir . DS . 'token';

        $this->cleanContentFromDir($pidFilesDir);
        $this->cleanContentFromDir($tokenFilesDir);

        $this->runRunner();

        self::assertCount($expectedPidCount, $this->extractContentFromDirFiles($pidFilesDir));
        self::assertCount(min([self::NUMBER_OF_CLASS_TESTS_FOR_BATCH_SIZE, $processes]), $this->extractContentFromDirFiles($tokenFilesDir));
    }

    /** @return iterable<array{int,?int,int}> */
    public static function provideForWrapperRunnerHandlesBatchSize(): iterable
    {
        yield 'One process with batchsize = null should have 1 pids and 1 token' =>  [1, null, 1];
        yield 'One process with batchsize = 0 should have 1 pids and 1 token' =>  [1, 0, 1];
        yield 'One process with batchsize = 1 should have 4 pids and 1 token' =>  [1, 1, 4];
        yield 'One process with batchsize = 2 should have 2 pids and 1 token' =>  [1, 2, 2];
        yield 'Two processes with batchsize = 2 should have 2 pids and 2 tokens' =>  [2, 2, 2];
    }

    private function cleanContentFromDir(string $path): void
    {
        $cleanableFiles = array_diff(scandir($path), self::UNPROCESSABLE_FILENAMES);
        foreach ($cleanableFiles as $cleanableFile) {
            unlink($path . DS . $cleanableFile);
        }
    }

    /** @return array<string> */
    private function extractContentFromDirFiles(string $path): array
    {
        $res              = [];
        $processableFiles = array_diff(scandir($path), self::UNPROCESSABLE_FILENAMES);
        self::assertCount(self::NUMBER_OF_CLASS_TESTS_FOR_BATCH_SIZE, $processableFiles);
        foreach ($processableFiles as $processableFile) {
            $res[] = file_get_contents($path . DS . $processableFile);
        }

        return array_unique($res);
    }

    /**
     * @group github
     * @coversNothing
     */
    public function testReadPhpunitConfigPhpSectionBeforeLoadingTheSuite(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('github' . DS . 'GH420' . DS . 'phpunit.xml');
        $runnerResult = $this->runRunner();
        $this->assertTestsPassed($runnerResult);
    }

    public function testRunnerSortTestEqualBySeed(): void
    {
        $this->bareOptions = [
            '--no-configuration' => true,
            'path' => $this->fixture('common_results'),
            '--order-by' => 'random',
            '--random-order-seed' => '123',
            '--verbose' => true,
        ];

        $runnerResultFirst = $this->runRunner();
        $runnerResultSecond = $this->runRunner();

        $firstOutput = $this->prepareOutputForTestOrderCheck($runnerResultFirst->getOutput());
        $secondOutput = $this->prepareOutputForTestOrderCheck($runnerResultSecond->getOutput());
        Assert::assertSame($firstOutput, $secondOutput);

        $this->bareOptions['--random-order-seed'] = '321';

        $runnerResultThird = $this->runRunner();

        $thirdOutput = $this->prepareOutputForTestOrderCheck($runnerResultThird->getOutput());

        Assert::assertNotSame($thirdOutput, $firstOutput);
    }

    /** @return string[] */
    private function prepareOutputForTestOrderCheck(string $output): array
    {
        $matchesCount = preg_match_all('/executing: (?<filename>\S+\.php)/',$output,$matches);

        Assert::assertGreaterThan(0, $matchesCount);

        return $matches['filename'];
    }

    public function testRunnerSortNoSeedProvided(): void
    {
        $this->bareOptions = [
            '--no-configuration' => true,
            'path' => $this->fixture('common_results'),
            '--order-by' => 'random',
            '--verbose' => true,
        ];

        $runnerResult = $this->runRunner();

        Assert::assertStringContainsString('Random Seed:', $runnerResult->getOutput());
    }

    /**
     * @group github
     * @coversNothing
     */
    public function testErrorsInDataProviderAreHandled(): void
    {
        self::markTestSkipped('Test is correct, but PHPUnit singletons mess things up');

        $this->bareOptions['--configuration'] = $this->fixture('github' . DS . 'GH565' . DS . 'phpunit.xml');
        $runnerResult = $this->runRunner();

        Assert::assertStringContainsString('The data provider specified for ParaTest\Tests\fixtures\github\GH565\IssueTest::testIncompleteByDataProvider is invalid', $runnerResult->getOutput());
        Assert::assertStringContainsString('The data provider specified for ParaTest\Tests\fixtures\github\GH565\IssueTest::testSkippedByDataProvider is invalid', $runnerResult->getOutput());
        Assert::assertStringContainsString('The data provider specified for ParaTest\Tests\fixtures\github\GH565\IssueTest::testErrorByDataProvider is invalid', $runnerResult->getOutput());
        Assert::assertStringContainsString('Warnings: 1', $runnerResult->getOutput());
        Assert::assertEquals(RunnerInterface::EXCEPTION_EXIT, $runnerResult->getExitCode());
    }

    public function testParatestEnvironmentVariableWithWrapperRunnerWithoutTestTokens(): void
    {
        $this->bareOptions['path'] = $this->fixture('paratest_only_tests' . DS . 'EnvironmentTest.php');
        $this->bareOptions['--no-test-tokens'] = true;

        $runnerResult = $this->runRunner();

        Assert::assertStringContainsString('Failures: 1', $runnerResult->getOutput());
        Assert::assertEquals(RunnerInterface::FAILURE_EXIT, $runnerResult->getExitCode());
    }

    public function testParatestEnvironmentVariable(): void
    {
        $this->bareOptions['path'] = $this->fixture('paratest_only_tests' . DS . 'EnvironmentTest.php');

        static::assertEquals(0, $this->runRunner()->getExitCode());
    }

    public function testPassthrus(): void
    {
        $this->bareOptions['path'] = $this->fixture('passthru_tests' . DS . 'PassthruTest.php');

        $runnerResult = $this->runRunner();
        Assert::assertSame(RunnerInterface::FAILURE_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--passthru-php'] = sprintf("'-d' 'highlight.comment=%s'", self::PASSTHRU_PHP_CUSTOM);
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->bareOptions['--passthru-php'] = str_replace('\'', '"', (string)$this->bareOptions['--passthru-php']);
        }

        $runnerResult = $this->runRunner();
        static::assertEquals(0, $runnerResult->getExitCode());
    }

    /**
     * @group github
     * @coversNothing
     */
    public function testReadPhpunitConfigPhpSectionBeforeLoadingTheSuiteManualBootstrap(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('github' . DS . 'GH420bis' . DS . 'phpunit.xml');
        $this->bareOptions['--bootstrap'] = $this->fixture('github' . DS . 'GH420bis' . DS . 'bootstrap.php');

        $runnerResult = $this->runRunner();
        static::assertEquals(0, $runnerResult->getExitCode());
    }

    public function testTeamcityOutput(): void
    {
        self::markTestIncomplete();
        $this->bareOptions = [
            '--configuration' => $this->fixture('phpunit-passing.xml'),
            '--teamcity' => true,
        ];

        $result = $this->runRunner();

        Assert::assertSame(66, preg_match_all('/^##teamcity/m', $result->getOutput()));
    }

    public function testExitCodesPathWithoutTests(): void
    {
        $this->bareOptions['path'] = $this->fixture('no_tests');
        $runnerResult = $this->runRunner();

        Assert::assertEquals(RunnerInterface::SUCCESS_EXIT, $runnerResult->getExitCode());
    }

    /** @requires OSFAMILY Linux */
    public function testTeamcityLogHandlesFifoFiles(): void
    {
        self::markTestIncomplete();
        $outputPath = $this->tmpDir . DS . 'test-output.teamcity';

        posix_mkfifo($outputPath, 0600);
        $this->bareOptions = [
            '--configuration' => $this->fixture('phpunit-passing.xml'),
            '--log-teamcity' => $outputPath,
        ];

        $fifoReader = new Process(['cat', $outputPath]);
        $fifoReader->start();

        $this->runRunner();

        Assert::assertSame(0, $fifoReader->wait());
        Assert::assertSame(66, preg_match_all('/^##teamcity/m', $fifoReader->getOutput()));
    }

    public function testStopOnFailureEndsRunBeforeWholeTestSuite(): void
    {
        $this->bareOptions['--processes'] = '1';
        $this->bareOptions['path'] = $this->fixture('common_results');
        $runnerResult = $this->runRunner();

        $regexp = '/Tests: \d+, Assertions: \d+, Errors: \d+, Failures: \d+, Warnings: \d+, Skipped: \d+, Incomplete: \d+, Risky: \d+\./';
        $output = $runnerResult->getOutput();
        Assert::assertMatchesRegularExpression($regexp, $output);
        Assert::assertSame(1, preg_match($regexp, $output, $matchesOnFullRun));

        $this->bareOptions['--stop-on-failure'] = true;
        $runnerResult = $this->runRunner();

        $regexp = '/Tests: \d+, Assertions: \d+, Errors: \d+, Skipped: \d+, Incomplete: \d+\./';
        $output = $runnerResult->getOutput();
        Assert::assertMatchesRegularExpression($regexp, $output);
        Assert::assertSame(1, preg_match($regexp, $output, $matchesOnPartialRun));

        Assert::assertNotEquals($matchesOnFullRun[0], $matchesOnPartialRun[0]);
    }

    public function testRaiseExceptionWhenATestCallsExitWithoutCoverageSingleProcess(): void
    {
        $this->bareOptions['path'] = $this->fixture('exit_tests');
        $this->bareOptions['--processes'] = '1';

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExits(Silently|Loudly)Test/');

        $this->runRunner();
    }

    public function testRaiseExceptionWhenATestCallsExitSilentlyWithCoverage(): void
    {
        $this->bareOptions['path'] = $this->fixture('exit_tests' . DS . 'UnitTestThatExitsSilentlyTest.php');
        $this->bareOptions['--coverage-php'] = $this->tmpDir . DS . uniqid('result_');
        $this->bareOptions['--coverage-filter'] = $this->fixture('exit_tests');
        $this->bareOptions['--cache-directory'] = $this->tmpDir;

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsSilentlyTest/');

        $this->runRunner();
    }

    public function testRaiseExceptionWhenATestCallsExitSilentlyWithoutCoverage(): void
    {
        $this->bareOptions['path'] = $this->fixture('exit_tests' . DS . 'UnitTestThatExitsSilentlyTest.php');

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsSilentlyTest/');

        $this->runRunner();
    }

    public function testRaiseExceptionWhenATestCallsExitLoudlyWithCoverage(): void
    {
        $this->bareOptions['path'] = $this->fixture('exit_tests' . DS . 'UnitTestThatExitsLoudlyTest.php');
        $this->bareOptions['--coverage-php'] = $this->tmpDir . DS . uniqid('result_');
        $this->bareOptions['--coverage-filter'] = $this->fixture('exit_tests');
        $this->bareOptions['--cache-directory'] = $this->tmpDir;

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsLoudlyTest/');

        $this->runRunner();
    }

    public function testRaiseExceptionWhenATestCallsExitLoudlyWithoutCoverage(): void
    {
        $this->bareOptions['path'] = $this->fixture('exit_tests' . DS . 'UnitTestThatExitsLoudlyTest.php');

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsLoudlyTest/');

        $this->runRunner();
    }

    public function testExitCodes(): void
    {
        $this->bareOptions['path'] = $this->fixture('common_results' . DS . 'ErrorTest.php');
        $runnerResult = $this->runRunner();

        Assert::assertStringContainsString('Errors: 1', $runnerResult->getOutput());
        Assert::assertEquals(RunnerInterface::EXCEPTION_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['path'] = $this->fixture('common_results' . DS . 'FailureTest.php');
        $runnerResult = $this->runRunner();

        Assert::assertStringContainsString('Failures: 1', $runnerResult->getOutput());
        Assert::assertEquals(RunnerInterface::FAILURE_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['path'] = $this->fixture('common_results' . DS . 'SuccessTest.php');
        $runnerResult = $this->runRunner();

        Assert::assertStringContainsString('OK', $runnerResult->getOutput());
        Assert::assertEquals(RunnerInterface::SUCCESS_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['path'] = $this->fixture('common_results');
        $runnerResult = $this->runRunner();

        Assert::assertStringContainsString('Failures: 1', $runnerResult->getOutput());
        Assert::assertStringContainsString('Errors: 1', $runnerResult->getOutput());
        Assert::assertEquals(RunnerInterface::EXCEPTION_EXIT, $runnerResult->getExitCode());
    }

    public function testWritesLogWithEmptyNameWhenPathIsNotProvided(): void
    {
        $outputPath = $this->tmpDir . DS . 'test-output.xml';

        $this->bareOptions = [
            '--configuration' => $this->fixture('phpunit-common_results.xml'),
            '--log-junit' => $outputPath,
        ];

        $this->runRunner();

        Assert::assertFileExists($outputPath);
        $doc = simplexml_load_file($outputPath);
        Assert::assertNotFalse($doc);
        $suites = (array)$doc->children();
        Assert::assertArrayHasKey('testsuite', $suites);
        $attribues = (array)$suites['testsuite']->attributes();
        Assert::assertArrayHasKey('@attributes', $attribues);
        Assert::assertIsArray($attribues['@attributes']);
        Assert::assertArrayHasKey('name', $attribues['@attributes']);
        Assert::assertSame('', $attribues['@attributes']['name']);
    }

    public function testRunnerReversed(): void
    {
        $this->bareOptions = [
            '--verbose' => true,
            'path' => $this->fixture('common_results'),
        ];

        $runnerResult = $this->runRunner();
        $defaultOrder = $this->prepareOutputForTestOrderCheck($runnerResult->getOutput());

        $this->bareOptions['--order-by'] = 'reverse';

        $runnerResult = $this->runRunner();
        $reverseOrder = $this->prepareOutputForTestOrderCheck($runnerResult->getOutput());

        $reverseOrderReversed = array_reverse($reverseOrder);

        Assert::assertSame($defaultOrder, $reverseOrderReversed);
    }

    /**
     * @group github
     * @coversNothing
     */
    public function testTokensAreAbsentWhenNoTestTokensIsSpecified(): void
    {
        $this->bareOptions['--no-test-tokens'] = true;
        $this->bareOptions['--processes'] = '1';
        $this->bareOptions['path'] = $this->fixture('github' . DS . 'GH505');

        $runnerResult = $this->runRunner();
        static::assertEquals(0, $runnerResult->getExitCode());
    }

    /**
     * A change in '--random-order-seed' must be reflected too in:
     *
     * @see \ParaTest\Tests\fixtures\deterministic_random\MtRandTest::testMtRandIsDeterministic
     */
    public function testRandomnessIsDeterministic(): void
    {
        $this->bareOptions = [
            '--order-by' => 'random',
            '--random-order-seed' => '123',
            'path' => $this->fixture('deterministic_random'),
        ];

        $runnerResult = $this->runRunner();
        static::assertEquals(0, $runnerResult->getExitCode());
    }

    public function testTeamcityLog(): void
    {
        self::markTestIncomplete();
        $outputPath = $this->tmpDir . DS . 'test-output.teamcity';

        $this->bareOptions = [
            '--configuration' => $this->fixture('phpunit-passing.xml'),
            '--log-teamcity' => $outputPath,
        ];

        $this->runRunner();

        Assert::assertFileExists($outputPath);
        $content = file_get_contents($outputPath);
        Assert::assertNotFalse($content);

        Assert::assertSame(66, preg_match_all('/^##teamcity/m', $content));
    }

    public function testRunningFewerTestsThanTheWorkersIsPossible(): void
    {
        $this->bareOptions['path'] = $this->fixture('common_results' . DS . 'SuccessTest.php');
        $this->bareOptions['--processes'] = '10';

        $runnerResult = $this->runRunner();
        static::assertEquals(0, $runnerResult->getExitCode());
    }

    public function testResultsAreCorrect(): void
    {
        $this->bareOptions['path'] = $this->fixture('common_results' . DS . 'SuccessTest.php');
        $this->bareOptions['--coverage-php'] = $this->tmpDir . DS . uniqid('result_');
        $this->bareOptions['--coverage-filter'] = $this->fixture('common_results');
        $this->bareOptions['--cache-directory'] = $this->tmpDir;

        $runnerResult = $this->runRunner();
        static::assertEquals(0, $runnerResult->getExitCode());

        $coveragePhp = include $this->bareOptions['--coverage-php'];
        Assert::assertInstanceOf(CodeCoverage::class, $coveragePhp);
    }
}
