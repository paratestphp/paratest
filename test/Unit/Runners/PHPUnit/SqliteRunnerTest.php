<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\SqliteRunner;
use ParaTest\Tests\Functional\RunnerResult;
use ParaTest\Tests\TestBase;
use PHPUnit\TextUI\TestRunner;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

use function extension_loaded;

/**
 * @covers \ParaTest\Runners\PHPUnit\BaseWrapperRunner
 * @covers \ParaTest\Runners\PHPUnit\SqliteRunner
 * @covers \ParaTest\Runners\PHPUnit\Worker\BaseWorker
 * @covers \ParaTest\Runners\PHPUnit\Worker\SqliteWorker
 */
final class SqliteRunnerTest extends TestBase
{
    /** @var array<string, string|bool|int> */
    private $bareOptions;

    protected function setUpTest(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            static::markTestSkipped('Skipping test: Extension pdo_sqlite not found.');
        }

        $this->bareOptions = ['--tmp-dir' => TMP_DIR];
    }

    private function runSqliteRunner(): RunnerResult
    {
        $output       = new BufferedOutput();
        $sqliteRunner = new SqliteRunner($this->createOptionsFromArgv($this->bareOptions), $output);
        $sqliteRunner->run();

        return new RunnerResult($sqliteRunner->getExitCode(), $output->fetch());
    }

    public function testResultsAreCorrect(): void
    {
        $this->bareOptions['--path'] = $this->fixture('passing-tests' . DS . 'GroupsTest.php');

        $this->assertTestsPassed($this->runSqliteRunner());
    }

    public function testRunningFewerTestsThanTheWorkersIsPossible(): void
    {
        $this->bareOptions['--path']      = $this->fixture('passing-tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--processes'] = 2;

        $this->assertTestsPassed($this->runSqliteRunner());
    }

    public function testExitCodes(): void
    {
        $this->bareOptions['--path'] = $this->fixture('wrapper-runner-exit-code-tests' . DS . 'ErrorTest.php');
        $runnerResult                = $this->runSqliteRunner();

        static::assertStringContainsString('Tests: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Failures: 0', $runnerResult->getOutput());
        static::assertStringContainsString('Errors: 1', $runnerResult->getOutput());
        static::assertEquals(TestRunner::EXCEPTION_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--path'] = $this->fixture('wrapper-runner-exit-code-tests' . DS . 'FailureTest.php');
        $runnerResult                = $this->runSqliteRunner();

        static::assertStringContainsString('Tests: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Failures: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Errors: 0', $runnerResult->getOutput());
        static::assertEquals(TestRunner::FAILURE_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--path'] = $this->fixture('wrapper-runner-exit-code-tests' . DS . 'SuccessTest.php');
        $runnerResult                = $this->runSqliteRunner();

        static::assertStringContainsString('OK (1 test, 1 assertion)', $runnerResult->getOutput());
        static::assertEquals(TestRunner::SUCCESS_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--path'] = $this->fixture('wrapper-runner-exit-code-tests');
        $runnerResult                = $this->runSqliteRunner();

        static::assertStringContainsString('Tests: 3', $runnerResult->getOutput());
        static::assertStringContainsString('Failures: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Errors: 1', $runnerResult->getOutput());
        static::assertEquals(TestRunner::EXCEPTION_EXIT, $runnerResult->getExitCode());
    }

    public function testRaiseExceptionWhenATestCallsExit(): void
    {
        $this->bareOptions['--path'] = $this->fixture('exit-tests' . DS . 'UnitTestThatExitsSilentlyTest.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsSilentlyTest/');

        $this->runSqliteRunner();
    }
}
