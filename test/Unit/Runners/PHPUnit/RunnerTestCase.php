<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\WorkerCrashedException;
use ParaTest\Tests\TestBase;
use PHPUnit\TextUI\TestRunner;
use SebastianBergmann\CodeCoverage\CodeCoverage;

use function array_merge;
use function sprintf;
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

    final public function testRaiseExceptionWhenATestCallsExitSilently(): void
    {
        $this->bareOptions['--path']         = $this->fixture('exit-tests' . DS . 'UnitTestThatExitsSilentlyTest.php');
        $this->bareOptions['--coverage-php'] = TMP_DIR . DS . uniqid('result_');

        $this->expectException(WorkerCrashedException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsSilentlyTest/');

        $this->runRunner();
    }

    final public function testRaiseExceptionWhenATestCallsExitLoudly(): void
    {
        $this->bareOptions['--path']         = $this->fixture('exit-tests' . DS . 'UnitTestThatExitsLoudlyTest.php');
        $this->bareOptions['--coverage-php'] = TMP_DIR . DS . uniqid('result_');

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

        $runnerResult = $this->runRunner();
        $this->assertTestsPassed($runnerResult);
    }
}
