<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\WrapperRunner;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\JUnit\TestSuite;
use ParaTest\RunnerInterface;
use ParaTest\Tests\TestBase;
use ParaTest\WrapperRunner\ResultPrinter;
use ParaTest\WrapperRunner\WorkerCrashedException;
use ParaTest\WrapperRunner\WrapperRunner;
use ParaTest\WrapperRunner\WrapperWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\Attributes\RequiresPhpunit;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use Symfony\Component\Process\Process;

use function array_diff;
use function array_reverse;
use function array_unique;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function implode;
use function is_file;
use function min;
use function posix_mkfifo;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function scandir;
use function sort;
use function sprintf;
use function str_replace;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const FIXTURES;
use const PHP_EOL;

/** @internal */
#[CoversClass(WrapperRunner::class)]
#[CoversClass(WrapperWorker::class)]
#[CoversClass(WorkerCrashedException::class)]
#[CoversClass(ResultPrinter::class)]
#[CoversClass(CoverageMerger::class)]
#[CoversClass(TestSuite::class)]
final class WrapperRunnerTest extends TestBase
{
    protected const NUMBER_OF_CLASS_TESTS_FOR_BATCH_SIZE = 4;
    protected const UNPROCESSABLE_FILENAMES              =  ['..', '.', '.gitignore'];

    public const PASSTHRU_PHP_CUSTOM = 'PASSTHRU_PHP_CUSTOM';

    #[DataProvider('provideForWrapperRunnerHandlesBatchSize')]
    public function testWrapperRunnerHandlesBatchSize(int $processes, ?int $batchSize, int $expectedPidCount): void
    {
        $this->bareOptions['--no-configuration'] = true;
        $this->bareOptions['--processes']        = (string) $processes;
        $this->bareOptions['path']               = $this->fixture('wrapper_batchsize_suite');
        if ($batchSize !== null) {
            $this->bareOptions['--max-batch-size'] = (string) $batchSize;
        }

        $tmpDir        = FIXTURES . DIRECTORY_SEPARATOR . 'wrapper_batchsize_suite' . DIRECTORY_SEPARATOR . 'tmp';
        $pidFilesDir   = $tmpDir . DIRECTORY_SEPARATOR . 'pid';
        $tokenFilesDir = $tmpDir . DIRECTORY_SEPARATOR . 'token';

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
        $array = scandir($path);
        self::assertNotFalse($array);
        $cleanableFiles = array_diff($array, self::UNPROCESSABLE_FILENAMES);
        foreach ($cleanableFiles as $cleanableFile) {
            unlink($path . DIRECTORY_SEPARATOR . $cleanableFile);
        }
    }

    /** @return array<string> */
    private function extractContentFromDirFiles(string $path): array
    {
        $array = scandir($path);
        self::assertNotFalse($array);
        $processableFiles = array_diff($array, self::UNPROCESSABLE_FILENAMES);
        self::assertCount(self::NUMBER_OF_CLASS_TESTS_FOR_BATCH_SIZE, $processableFiles);
        $res = [];
        foreach ($processableFiles as $processableFile) {
            $contents = file_get_contents($path . DIRECTORY_SEPARATOR . $processableFile);
            self::assertNotFalse($contents);

            $res[] = $contents;
        }

        return array_unique($res);
    }

    #[Group('github')]
    #[CoversNothing]
    public function testReadPhpunitConfigPhpSectionBeforeLoadingTheSuite(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('github' . DIRECTORY_SEPARATOR . 'GH420' . DIRECTORY_SEPARATOR . 'phpunit.xml');
        $runnerResult                         = $this->runRunner();
        self::assertEquals(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
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

        $runnerResultFirst  = $this->runRunner();
        $runnerResultSecond = $this->runRunner();

        $firstOutput  = $this->prepareOutputForTestOrderCheck($runnerResultFirst->output);
        $secondOutput = $this->prepareOutputForTestOrderCheck($runnerResultSecond->output);
        self::assertSame($firstOutput, $secondOutput);

        $this->bareOptions['--random-order-seed'] = '321';

        $runnerResultThird = $this->runRunner();

        $thirdOutput = $this->prepareOutputForTestOrderCheck($runnerResultThird->output);

        self::assertNotSame($thirdOutput, $firstOutput);
    }

    /** @return string[] */
    private function prepareOutputForTestOrderCheck(string $output): array
    {
        $matchesCount = preg_match_all('/executing: (?<filename>\S+\.php)/', $output, $matches);

        self::assertGreaterThan(0, $matchesCount);

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

        self::assertStringContainsString('Random Seed:', $runnerResult->output);
    }

    #[Group('github')]
    #[CoversNothing]
    public function testErrorsInDataProviderAreHandled(): void
    {
        self::markTestSkipped('Test is correct, but PHPUnit singletons mess things up');

        $this->bareOptions['--configuration'] = $this->fixture('github' . DIRECTORY_SEPARATOR . 'GH565' . DIRECTORY_SEPARATOR . 'phpunit.xml');
        $runnerResult                         = $this->runRunner();

        self::assertStringContainsString('The data provider specified for ParaTest\Tests\fixtures\github\GH565\IssueTest::testIncompleteByDataProvider is invalid', $runnerResult->output);
        self::assertStringContainsString('The data provider specified for ParaTest\Tests\fixtures\github\GH565\IssueTest::testSkippedByDataProvider is invalid', $runnerResult->output);
        self::assertStringContainsString('The data provider specified for ParaTest\Tests\fixtures\github\GH565\IssueTest::testErrorByDataProvider is invalid', $runnerResult->output);
        self::assertStringContainsString('Warnings: 1', $runnerResult->output);
        self::assertEquals(RunnerInterface::EXCEPTION_EXIT, $runnerResult->exitCode);
    }

    public function testParatestEnvironmentVariableWithWrapperRunnerWithoutTestTokens(): void
    {
        $this->bareOptions['path']             = $this->fixture('paratest_only_tests' . DIRECTORY_SEPARATOR . 'EnvironmentTest.php');
        $this->bareOptions['--no-test-tokens'] = true;

        $runnerResult = $this->runRunner();

        self::assertStringContainsString('Failures: 1', $runnerResult->output);
        self::assertSame(RunnerInterface::FAILURE_EXIT, $runnerResult->exitCode);
    }

    public function testParatestEnvironmentVariable(): void
    {
        $this->bareOptions['path'] = $this->fixture('paratest_only_tests' . DIRECTORY_SEPARATOR . 'EnvironmentTest.php');

        self::assertSame(0, $this->runRunner()->exitCode);
    }

    public function testPassthrus(): void
    {
        $this->bareOptions['path'] = $this->fixture('passthru_tests' . DIRECTORY_SEPARATOR . 'PassthruTest.php');

        $runnerResult = $this->runRunner();
        self::assertSame(RunnerInterface::FAILURE_EXIT, $runnerResult->exitCode);

        $this->bareOptions['--passthru-php'] = sprintf("'-d' 'highlight.comment=%s'", self::PASSTHRU_PHP_CUSTOM);

        $runnerResult = $this->runRunner();
        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    #[Group('github')]
    #[CoversNothing]
    public function testReadPhpunitConfigPhpSectionBeforeLoadingTheSuiteManualBootstrap(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('github' . DIRECTORY_SEPARATOR . 'GH420bis' . DIRECTORY_SEPARATOR . 'phpunit.xml');
        $this->bareOptions['--bootstrap']     = $this->fixture('github' . DIRECTORY_SEPARATOR . 'GH420bis' . DIRECTORY_SEPARATOR . 'bootstrap.php');

        $runnerResult = $this->runRunner();
        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    public function testTeamcityOutput(): void
    {
        $this->bareOptions['path']       = $this->fixture('common_results');
        $this->bareOptions['--teamcity'] = true;

        $result = $this->runRunner();

        $format = file_get_contents(__DIR__ . '/fixtures/common_results_teamcity_output');
        self::assertNotFalse($format);

        $output = $result->output;
        $output = preg_replace("/^Processes:     \\d+\nRuntime:       PHP \\d+\\.\\d+\\.\\w+(-\w+)?\n\n/", '', $output, 1, $count);
        self::assertSame(1, $count);
        self::assertNotNull($output);

        self::assertStringMatchesFormat(
            self::sorted($format),
            self::sorted($output),
        );
    }

    public function testTestdoxOutput(): void
    {
        $this->bareOptions['path']      = $this->fixture('common_results');
        $this->bareOptions['--testdox'] = true;

        $result = $this->runRunner();

        $format = file_get_contents(__DIR__ . '/fixtures/common_results_testdox_output');
        self::assertNotFalse($format);

        $output = $result->output;
        $output = preg_replace("/^Processes:     \\d+\nRuntime:       PHP \\d+\\.\\d+\\.\\w+(-.+)?\n\n/", '', $output, 1, $count);
        self::assertSame(1, $count);
        self::assertNotNull($output);

        self::assertStringMatchesFormat(
            $format,
            $output,
        );
    }

    public function testJunitOutputWithoutTests(): void
    {
        $this->bareOptions['path']        = $this->fixture('no_tests');
        $this->bareOptions['--log-junit'] = $this->tmpDir . DIRECTORY_SEPARATOR . 'test-output.xml';
        $runnerResult                     = $this->runRunner();

        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    public function testExitCodesPathWithoutTests(): void
    {
        $this->bareOptions['path'] = $this->fixture('no_tests');
        $runnerResult              = $this->runRunner();

        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testTeamcityLogHandlesFifoFiles(): void
    {
        $outputPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'test-output.teamcity';

        posix_mkfifo($outputPath, 0600);
        $this->bareOptions['path']           = $this->fixture('common_results' . DIRECTORY_SEPARATOR . 'SuccessTest.php');
        $this->bareOptions['--log-teamcity'] = $outputPath;

        $fifoReader = new Process(['cat', $outputPath]);
        $fifoReader->start();

        $this->runRunner();

        self::assertSame(0, $fifoReader->wait());
        self::assertSame(5, preg_match_all('/^##teamcity/m', $fifoReader->getOutput()));
    }

    public function testStopOnFailureEndsRunBeforeWholeTestSuite(): void
    {
        $regex = '/Tests: (?<tests>\d+),/';

        $this->bareOptions['--processes'] = '1';
        $this->bareOptions['path']        = $this->fixture('common_results');
        $output                           = $this->runRunner()->output;
        self::assertMatchesRegularExpression($regex, $output);
        self::assertSame(1, preg_match($regex, $output, $matches));
        $testsBefore = (int) $matches['tests'];
        self::assertGreaterThan(0, $testsBefore);

        $this->bareOptions['--stop-on-failure'] = true;
        $output                                 = $this->runRunner()->output;
        self::assertMatchesRegularExpression($regex, $output);
        self::assertSame(1, preg_match($regex, $output, $matches));
        $testsAfter = (int) $matches['tests'];
        self::assertGreaterThan(0, $testsAfter);

        self::assertLessThan($testsBefore, $testsAfter);
    }

    public function testRaiseExceptionWhenATestCallsExitWithoutCoverageSingleProcess(): void
    {
        $this->bareOptions['path']        = $this->fixture('exit_tests');
        $this->bareOptions['--processes'] = '1';

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExits(Silently|Loudly)Test/');

        $this->runRunner();
    }

    public function testRaiseExceptionWhenATestCallsExitSilentlyWithCoverage(): void
    {
        $this->bareOptions['path']              = $this->fixture('exit_tests' . DIRECTORY_SEPARATOR . 'UnitTestThatExitsSilentlyTest.php');
        $this->bareOptions['--coverage-php']    = $this->tmpDir . DIRECTORY_SEPARATOR . uniqid('result_');
        $this->bareOptions['--coverage-filter'] = $this->fixture('exit_tests');
        $this->bareOptions['--cache-directory'] = $this->tmpDir;

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsSilentlyTest/');

        $this->runRunner();
    }

    public function testRaiseExceptionWhenATestCallsExitSilentlyWithoutCoverage(): void
    {
        $this->bareOptions['path'] = $this->fixture('exit_tests' . DIRECTORY_SEPARATOR . 'UnitTestThatExitsSilentlyTest.php');

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsSilentlyTest/');

        $this->runRunner();
    }

    public function testRaiseExceptionWhenATestCallsExitLoudlyWithCoverage(): void
    {
        $this->bareOptions['path']              = $this->fixture('exit_tests' . DIRECTORY_SEPARATOR . 'UnitTestThatExitsLoudlyTest.php');
        $this->bareOptions['--coverage-php']    = $this->tmpDir . DIRECTORY_SEPARATOR . uniqid('result_');
        $this->bareOptions['--coverage-filter'] = $this->fixture('exit_tests');
        $this->bareOptions['--cache-directory'] = $this->tmpDir;

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsLoudlyTest/');

        $this->runRunner();
    }

    public function testRaiseExceptionWhenATestCallsExitLoudlyWithoutCoverage(): void
    {
        $this->bareOptions['path'] = $this->fixture('exit_tests' . DIRECTORY_SEPARATOR . 'UnitTestThatExitsLoudlyTest.php');

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsLoudlyTest/');

        $this->runRunner();
    }

    public function testExitCodes(): void
    {
        $this->bareOptions['path'] = $this->fixture('common_results' . DIRECTORY_SEPARATOR . 'ErrorTest.php');
        $runnerResult              = $this->runRunner();

        self::assertStringContainsString('Errors: 1', $runnerResult->output);
        self::assertSame(RunnerInterface::EXCEPTION_EXIT, $runnerResult->exitCode);

        $this->bareOptions['path'] = $this->fixture('common_results' . DIRECTORY_SEPARATOR . 'FailureTest.php');
        $runnerResult              = $this->runRunner();

        self::assertStringContainsString('Failures: 1', $runnerResult->output);
        self::assertSame(RunnerInterface::FAILURE_EXIT, $runnerResult->exitCode);

        $this->bareOptions['path'] = $this->fixture('common_results' . DIRECTORY_SEPARATOR . 'SuccessTest.php');
        $runnerResult              = $this->runRunner();

        self::assertStringContainsString('OK', $runnerResult->output);
        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);

        $this->bareOptions['path'] = $this->fixture('common_results');
        $runnerResult              = $this->runRunner();

        self::assertStringContainsString('Failures: 1', $runnerResult->output);
        self::assertStringContainsString('Errors: 1', $runnerResult->output);
        self::assertSame(RunnerInterface::EXCEPTION_EXIT, $runnerResult->exitCode);
    }

    public function testWritesLogWithEmptyNameWhenPathIsNotProvided(): void
    {
        $outputFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'test-output.xml';

        $this->bareOptions = [
            '--configuration' => $this->fixture('phpunit-common_results.xml'),
            '--log-junit' => $outputFile,
        ];

        $this->runRunner();

        self::assertFileExists($outputFile);
        $xml = file_get_contents($outputFile);
        self::assertNotFalse($xml);
        $xml = str_replace(FIXTURES, './test/fixtures', $xml);
        $xml = preg_replace('/time="[^"]+"/', 'time="1.234567"', $xml);
        file_put_contents($outputFile, $xml);

        self::assertXmlFileEqualsXmlFile(FIXTURES . '/common_results/combined.xml', $outputFile);
    }

    public function testRunnerReversed(): void
    {
        $this->bareOptions = [
            '--verbose' => true,
            'path' => $this->fixture('common_results'),
        ];

        $runnerResult = $this->runRunner();
        $defaultOrder = $this->prepareOutputForTestOrderCheck($runnerResult->output);

        $this->bareOptions['--order-by'] = 'reverse';

        $runnerResult = $this->runRunner();
        $reverseOrder = $this->prepareOutputForTestOrderCheck($runnerResult->output);

        $reverseOrderReversed = array_reverse($reverseOrder);

        self::assertSame($defaultOrder, $reverseOrderReversed);
    }

    #[Group('github')]
    #[CoversNothing]
    public function testTokensAreAbsentWhenNoTestTokensIsSpecified(): void
    {
        $this->bareOptions['--no-test-tokens'] = true;
        $this->bareOptions['--processes']      = '1';
        $this->bareOptions['path']             = $this->fixture('github' . DIRECTORY_SEPARATOR . 'GH505');

        $runnerResult = $this->runRunner();
        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
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
        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    public function testTeamcityLog(): void
    {
        $outputPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'test-output.teamcity';

        $this->bareOptions['path']           = $this->fixture('common_results');
        $this->bareOptions['--log-teamcity'] = $outputPath;

        $this->runRunner();

        $format = file_get_contents(__DIR__ . '/fixtures/common_results_teamcity_output');
        self::assertNotFalse($format);

        self::assertFileExists($outputPath);
        $content = file_get_contents($outputPath);
        self::assertNotFalse($content);

        self::assertStringMatchesFormat(
            self::sorted($format),
            self::sorted($content),
        );
    }

    public function testRunningFewerTestsThanTheWorkersIsPossible(): void
    {
        $this->bareOptions['path']        = $this->fixture('common_results' . DIRECTORY_SEPARATOR . 'SuccessTest.php');
        $this->bareOptions['--processes'] = '10';

        $runnerResult = $this->runRunner();
        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
        $glob = glob($this->tmpDir . '/*');
        self::assertNotFalse($glob);
        self::assertCount(0, $glob);
    }

    public function testResultsAreCorrect(): void
    {
        $this->bareOptions['path']              = $this->fixture('common_results' . DIRECTORY_SEPARATOR . 'SuccessTest.php');
        $this->bareOptions['--coverage-php']    = $this->tmpDir . DIRECTORY_SEPARATOR . uniqid('result_');
        $this->bareOptions['--coverage-filter'] = $this->fixture('common_results');
        $this->bareOptions['--cache-directory'] = $this->tmpDir;

        $runnerResult = $this->runRunner();
        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);

        $coveragePhp = include $this->bareOptions['--coverage-php'];
        self::assertInstanceOf(CodeCoverage::class, $coveragePhp);
    }

    public function testHandleCollisionWithSymfonyOutput(): void
    {
        $this->bareOptions['path'] = $this->fixture('symfony_output_collision' . DIRECTORY_SEPARATOR . 'FailingSymfonyOutputCollisionTest.php');

        $runnerResult = $this->runRunner();
        self::assertStringContainsString('<bg=%s>', $runnerResult->output);
    }

    #[Group('github')]
    #[CoversNothing]
    #[RequiresPhpunit('10')]
    public function testIgnoreAttributes(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('github' . DIRECTORY_SEPARATOR . 'GH756' . DIRECTORY_SEPARATOR . 'phpunit.xml');
        $this->bareOptions['--processes']     = '1';
        $runnerResult                         = $this->runRunner();

        $expectedContains = <<<'EOF'
        ParaTest\Tests\fixtures\github\GH756\CoveredOneClass
          Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  1/  1)
        ParaTest\Tests\fixtures\github\GH756\CoveredTwoClass
          Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  1/  1)
        EOF;

        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
        self::assertStringContainsString($expectedContains, $runnerResult->output);
    }

    public function testHandleUnexpectedOutput(): void
    {
        $this->bareOptions['path'] = $this->fixture('unexpected_output' . DIRECTORY_SEPARATOR . 'UnexpectedOutputTest.php');

        $expectedOutput = <<<'EOF'
Processes:     2
Runtime:       PHP %s

foobar.                                                                   1 / 1 (100%)

Time: %s, Memory: %s MB

OK%a
EOF;

        $runnerResult = $this->runRunner();
        self::assertStringMatchesFormat($expectedOutput, $runnerResult->output);

        $this->bareOptions['--disallow-test-output'] = true;

        $expectedOutput = <<<'EOF'
Processes:     2
Runtime:       PHP %s

foobarR                                                                   1 / 1 (100%)

Time: %s, Memory: %s MB

There was 1 risky test:

1) ParaTest\Tests\fixtures\unexpected_output\UnexpectedOutputTest::testInvalidLogic
Test code or tested code printed unexpected output: foobar

%s/test/fixtures/unexpected_output/UnexpectedOutputTest.php:%d

OK, but there were issues!
%a
EOF;

        $runnerResult = $this->runRunner();
        self::assertStringMatchesFormat($expectedOutput, $runnerResult->output);
    }

    #[Group('github')]
    #[CoversNothing]
    public function testGroupOptionWithDataProviderAndCodeCoverageEnabled(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('github' . DIRECTORY_SEPARATOR . 'GH782' . DIRECTORY_SEPARATOR . 'phpunit.xml');
        $this->bareOptions['--group']         = 'default';

        $runnerResult = $this->runRunner();
        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    /**
     * \PHPUnit\Runner\Filter\NameFilterIterator uses `preg_match`, and in
     * \ParaTest\Tests\fixtures\function_parallelization_tests\FunctionalParallelizationTest::dataProvider2
     * on the second data name we explicitly test a NULL-byte for our internal implementation, but
     * NULL-byte isn't supported in PHP < 8.2
     *
     * @see https://bugs.php.net/bug.php?id=77726
     * @see https://github.com/php/php-src/pull/8114
     */
    public function testFunctionalParallelization(): void
    {
        $this->bareOptions['path']         = $this->fixture('function_parallelization_tests');
        $this->bareOptions['--functional'] = true;

        $runnerResult = $this->runRunner();

        $expectedOutput = <<<'EOF'
Processes:     2
Runtime:       PHP %s

....................                                              20 / 20 (100%)

Time: %s, Memory: %s MB

OK%a
EOF;
        self::assertStringMatchesFormat($expectedOutput, $runnerResult->output);
        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    public function testSameBeginningOfName(): void
    {
        $this->bareOptions['path']         = $this->fixture('same_beginning_of_name');
        $this->bareOptions['--functional'] = true;

        $runnerResult = $this->runRunner();

        $expectedOutput = <<<'EOF'
Processes:     2
Runtime:       PHP %s

....                                                                4 / 4 (100%)

Time: %s, Memory: %s MB

OK%a
EOF;
        self::assertStringMatchesFormat($expectedOutput, $runnerResult->output);
        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    public function testFunctionalParallelizationWithJunitLogging(): void
    {
        $outputFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'test-output.xml';

        $this->bareOptions['path']             = $this->fixture('function_parallelization_tests');
        $this->bareOptions['--processes']      = '1';
        $this->bareOptions['--functional']     = true;
        $this->bareOptions['--max-batch-size'] = 1;
        $this->bareOptions['--log-junit']      = $outputFile;

        $runnerResult = $this->runRunner();

        $expectedOutput = <<<'EOF'
Processes:     1
Runtime:       PHP %s

....................                                              20 / 20 (100%)

Time: %s, Memory: %s MB

OK%a
EOF;
        self::assertStringMatchesFormat($expectedOutput, $runnerResult->output);
        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    #[Group('github')]
    #[CoversNothing]
    public function testLongDataProviderShouldNotFillUnexpectedOutputFile(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('github' . DIRECTORY_SEPARATOR . 'GH853' . DIRECTORY_SEPARATOR . 'phpunit.xml');
        $runnerResult                         = $this->runRunner();

        self::assertStringNotContainsString('101 / 1000 ( 10%)  202 / 1000 ( 20%)', $runnerResult->output);
        self::assertEquals(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    #[Group('github')]
    #[CoversNothing]
    public function testCliOptionsThatCouldBeUsedMultipleTimes(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('github' . DIRECTORY_SEPARATOR . 'GH857' . DIRECTORY_SEPARATOR . 'phpunit.xml');
        $this->bareOptions['--group']         = [
            'one',
            'two',
        ];

        $runnerResult = $this->runRunner();

        $expectedOutput = <<<'EOF'
Processes:     %s
Runtime:       PHP %s
Configuration: %s

..                                                                  2 / 2 (100%)

Time: %s, Memory: %s MB

OK%a
EOF;
        self::assertStringMatchesFormat($expectedOutput, $runnerResult->output);
        self::assertEquals(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    #[Group('github')]
    public function testBaselineIsRespected(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('github' . DIRECTORY_SEPARATOR . 'GH869' . DIRECTORY_SEPARATOR . 'phpunit.xml');

        $runnerResult = $this->runRunner();

        $expectedOutput = <<<'EOF'
Processes:     %s
Runtime:       PHP %s
Configuration: %s

..                                                                  2 / 2 (100%)

Time: %s, Memory: %s MB

OK%a
EOF;
        self::assertStringMatchesFormat($expectedOutput, $runnerResult->output);
        self::assertEquals(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    #[Group('github')]
    public function testDeprecationsShouldNotRaiseException(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('github' . DIRECTORY_SEPARATOR . 'GH915' . DIRECTORY_SEPARATOR . 'phpunit.xml');

        $runnerResult = $this->runRunner();

        $expectedOutput = <<<'EOF'
Processes:     %s
Runtime:       PHP %s
Configuration: %s

.                                                                   1 / 1 (100%)

Time: %s, Memory: %s MB

OK%a
EOF;
        self::assertStringMatchesFormat($expectedOutput, $runnerResult->output);
        self::assertEquals(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    public function testExtensionMustRunBeforeDataProvider(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('extension_run_before_data_provider' . DIRECTORY_SEPARATOR . 'phpunit.xml');

        $runnerResult = $this->runRunner();

        $expectedOutput = <<<EOF
Processes:     %s
Runtime:       PHP %s
Configuration: {$this->bareOptions['--configuration']}

.                                                                   1 / 1 (100%)

Time: %s, Memory: %s MB

OK%a
EOF;
        self::assertStringMatchesFormat($expectedOutput, $runnerResult->output);
        self::assertEquals(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    public function testOrderByCache(): void
    {
        $resultCacheFile = $this->fixture('order_by') . DIRECTORY_SEPARATOR . '.phpunit.result.cache';
        if (is_file($resultCacheFile)) {
            unlink($resultCacheFile);
        }

        $this->bareOptions['--configuration'] = $this->fixture('order_by' . DIRECTORY_SEPARATOR . 'phpunit.xml');
        $this->bareOptions['--processes']     = '1';
        $this->bareOptions['--order-by']      = 'defects';

        $expectedOutput = static function (string $order): string {
            return 'Processes:     %s
Runtime:       PHP %s
Configuration: %s

' . $order . '                                                                  2 / 2 (100%)

Time: %s, Memory: %s MB

There was 1 failure:

1) ParaTest\Tests\fixtures\order_by\BFailingTest::testFailure
Failed asserting that false is true.

%s/test/fixtures/order_by/BFailingTest.php:14

FAILURES!
%s
';
        };

        $runnerResult = $this->runRunner();

        self::assertStringMatchesFormat($expectedOutput('.F'), $runnerResult->output);
        self::assertEquals(RunnerInterface::FAILURE_EXIT, $runnerResult->exitCode);

        self::assertFileExists($resultCacheFile);

        $runnerResult = $this->runRunner();

        self::assertStringMatchesFormat($expectedOutput('F.'), $runnerResult->output);
        self::assertEquals(RunnerInterface::FAILURE_EXIT, $runnerResult->exitCode);
    }

    /**
     * ###   WARNING   ###
     *
     * This test MUST be the last of this file,
     * otherwise the next one will always fail
     */
    public function testProcessIsolation(): void
    {
        $this->bareOptions['path']                = $this->fixture('process_isolation' . DIRECTORY_SEPARATOR . 'FooTest.php');
        $this->bareOptions['--process-isolation'] = true;

        $runnerResult = $this->runRunner();
        self::assertSame(RunnerInterface::SUCCESS_EXIT, $runnerResult->exitCode);
    }

    private static function sorted(string $from): string
    {
        $from = explode(PHP_EOL, $from);
        sort($from);

        return implode(PHP_EOL, $from);
    }
}
