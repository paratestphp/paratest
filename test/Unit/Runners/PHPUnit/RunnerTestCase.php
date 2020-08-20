<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\WorkerCrashedException;
use ParaTest\Tests\TestBase;
use PHPUnit\TextUI\TestRunner;
use SebastianBergmann\CodeCoverage\CodeCoverage;

use function array_merge;
use function defined;
use function preg_match;
use function sprintf;
use function str_replace;
use function substr_count;
use function uniqid;

abstract class RunnerTestCase extends TestBase
{
    public const PASSTHRU_PHPUNIT_CUSTOM = 'PASSTHRU_PHPUNIT_CUSTOM';
    public const PASSTHRU_PHP_CUSTOM     = 'PASSTHRU_PHP_CUSTOM';

    final public function testResultsAreCorrect(): void
    {
        $this->bareOptions['--path']         = $this->fixture('passing-tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--coverage-php'] = TMP_DIR . DS . uniqid('result_');

        $this->assertTestsPassed($this->runRunner());

        $coveragePhp = include $this->bareOptions['--coverage-php'];
        static::assertInstanceOf(CodeCoverage::class, $coveragePhp);
    }

    final public function testRunningFewerTestsThanTheWorkersIsPossible(): void
    {
        $this->bareOptions['--path']      = $this->fixture('passing-tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--processes'] = 2;

        $this->assertTestsPassed($this->runRunner());
    }

    final public function testExitCodes(): void
    {
        $this->bareOptions['--path'] = $this->fixture('wrapper-runner-exit-code-tests' . DS . 'ErrorTest.php');
        $runnerResult                = $this->runRunner();

        static::assertStringContainsString('Tests: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Failures: 0', $runnerResult->getOutput());
        static::assertStringContainsString('Errors: 1', $runnerResult->getOutput());
        static::assertEquals(TestRunner::EXCEPTION_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--path'] = $this->fixture('wrapper-runner-exit-code-tests' . DS . 'FailureTest.php');
        $runnerResult                = $this->runRunner();

        static::assertStringContainsString('Tests: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Failures: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Errors: 0', $runnerResult->getOutput());
        static::assertEquals(TestRunner::FAILURE_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--path'] = $this->fixture('wrapper-runner-exit-code-tests' . DS . 'SuccessTest.php');
        $runnerResult                = $this->runRunner();

        static::assertStringContainsString('OK (1 test, 1 assertion)', $runnerResult->getOutput());
        static::assertEquals(TestRunner::SUCCESS_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--path'] = $this->fixture('wrapper-runner-exit-code-tests');
        $runnerResult                = $this->runRunner();

        static::assertStringContainsString('Tests: 3', $runnerResult->getOutput());
        static::assertStringContainsString('Failures: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Errors: 1', $runnerResult->getOutput());
        static::assertEquals(TestRunner::EXCEPTION_EXIT, $runnerResult->getExitCode());
    }

    final public function testParallelSuiteOption(): void
    {
        $this->bareOptions = array_merge($this->bareOptions, [
            '--configuration' => $this->fixture('phpunit-parallel-suite.xml'),
            '--parallel-suite' => true,
            '--processes' => 2,
            '--verbose' => 1,
            '--whitelist' => $this->fixture('parallel-suite'),
        ]);

        $this->assertTestsPassed($this->runRunner());
    }

    final public function testRaiseExceptionWhenATestCallsExitSilentlyWithCoverage(): void
    {
        $this->bareOptions['--path']         = $this->fixture('exit-tests' . DS . 'UnitTestThatExitsSilentlyTest.php');
        $this->bareOptions['--coverage-php'] = TMP_DIR . DS . uniqid('result_');

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsSilentlyTest/');

        $this->runRunner();
    }

    final public function testRaiseExceptionWhenATestCallsExitLoudlyWithCoverage(): void
    {
        $this->bareOptions['--path']         = $this->fixture('exit-tests' . DS . 'UnitTestThatExitsLoudlyTest.php');
        $this->bareOptions['--coverage-php'] = TMP_DIR . DS . uniqid('result_');

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsLoudlyTest/');

        $this->runRunner();
    }

    final public function testRaiseExceptionWhenATestCallsExitSilentlyWithoutCoverage(): void
    {
        $this->bareOptions['--path'] = $this->fixture('exit-tests' . DS . 'UnitTestThatExitsSilentlyTest.php');

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsSilentlyTest/');

        $this->runRunner();
    }

    final public function testRaiseExceptionWhenATestCallsExitLoudlyWithoutCoverage(): void
    {
        $this->bareOptions['--path'] = $this->fixture('exit-tests' . DS . 'UnitTestThatExitsLoudlyTest.php');

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsLoudlyTest/');

        $this->runRunner();
    }

    final public function testPassthrus(): void
    {
        $this->bareOptions['--path'] = $this->fixture('passthru-tests' . DS . 'PassthruTest.php');

        $runnerResult = $this->runRunner();
        static::assertSame(TestRunner::FAILURE_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--passthru-php'] = sprintf("'-d' 'highlight.comment=%s'", self::PASSTHRU_PHP_CUSTOM);
        $this->bareOptions['--passthru']     = sprintf("'-d' 'highlight.string=%s'", self::PASSTHRU_PHPUNIT_CUSTOM);
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->bareOptions['--passthru']     = str_replace('\'', '"', (string) $this->bareOptions['--passthru']);
            $this->bareOptions['--passthru-php'] = str_replace('\'', '"', (string) $this->bareOptions['--passthru-php']);
        }

        $this->assertTestsPassed($this->runRunner());
    }

    final public function testGroupAndExcludeGroupArePassedToPhpunitEvenForNonFunctionTests(): void
    {
        $this->bareOptions['--path']          = $this->fixture('passing-tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--group']         = 'group1,group2';
        $this->bareOptions['--exclude-group'] = 'group3';

        $this->assertTestsPassed($this->runRunner(), '3', '3');
    }

    final public function testTestsWithWarningsResultInFailure(): void
    {
        $this->bareOptions['--path']          = $this->fixture('warning-tests' . DS . 'HasWarningsTest.php');
        $this->bareOptions['--configuration'] = $this->fixture('warning-tests' . DS . 'phpunit.xml.dist');

        $runnerResult = $this->runRunner();

        static::assertStringContainsString('Warnings', $runnerResult->getOutput());
        static::assertEquals(TestRunner::FAILURE_EXIT, $runnerResult->getExitCode());
    }

    final public function testTestsWithOtherWarningsResultInFailure(): void
    {
        $this->bareOptions['--path']          = $this->fixture('warning-tests' . DS . 'HasOtherWarningsTest.php');
        $this->bareOptions['--configuration'] = $this->fixture('warning-tests' . DS . 'phpunit.xml.dist');

        $runnerResult = $this->runRunner();

        static::assertStringContainsString('Warnings', $runnerResult->getOutput());
        static::assertEquals(TestRunner::FAILURE_EXIT, $runnerResult->getExitCode());
    }

    final public function testParatestEnvironmentVariable(): void
    {
        $this->bareOptions['--path'] = $this->fixture('paratest-only-tests' . DS . 'EnvironmentTest.php');

        $this->assertTestsPassed($this->runRunner());
    }

    final public function testParatestEnvironmentVariableWithWrapperRunnerWithoutTestTokens(): void
    {
        $this->bareOptions['--path']           = $this->fixture('paratest-only-tests' . DS . 'EnvironmentTest.php');
        $this->bareOptions['--no-test-tokens'] = true;

        $runnerResult = $this->runRunner();

        static::assertStringContainsString('Failures: 1', $runnerResult->getOutput());
        static::assertEquals(TestRunner::FAILURE_EXIT, $runnerResult->getExitCode());
    }

    final public function testSkippedInDefaultMode(): void
    {
        $this->bareOptions['--path'] = $this->fixture('skipped-tests' . DS . 'SkippedTest.php');

        $runnerResult = $this->runRunner();

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
            . 'Tests: 1, Assertions: 0, Incomplete: 1.';
        static::assertStringContainsString($expected, $runnerResult->getOutput());
        $this->assertContainsNSkippedTests(1, $runnerResult->getOutput());
    }

    final public function testIncompleteInDefaultMode(): void
    {
        $this->bareOptions['--path'] = $this->fixture('skipped-tests' . DS . 'IncompleteTest.php');

        $runnerResult = $this->runRunner();

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
            . 'Tests: 1, Assertions: 0, Incomplete: 1.';
        static::assertStringContainsString($expected, $runnerResult->getOutput());
        $this->assertContainsNSkippedTests(1, $runnerResult->getOutput());
    }

    final public function testDataProviderWithSkippedInDefaultMode(): void
    {
        $this->bareOptions['--path'] = $this->fixture('skipped-tests' . DS . 'SkippedAndIncompleteDataProviderTest.php');

        $runnerResult = $this->runRunner();

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
            . 'Tests: 100, Assertions: 33, Incomplete: 67.';
        static::assertStringContainsString($expected, $runnerResult->getOutput());
        $this->assertContainsNSkippedTests(67, $runnerResult->getOutput());
    }

    final protected function assertContainsNSkippedTests(int $n, string $output): void
    {
        preg_match('/\n\n([\.ISEF].*)\n\nTime/s', $output, $matches);
        static::assertCount(2, $matches);
        $numberOfS = substr_count($matches[1], 'S');
        static::assertEquals(
            $n,
            $numberOfS,
            "The test should have skipped {$n} tests, instead it skipped {$numberOfS}, {$matches[1]}"
        );
    }
}
