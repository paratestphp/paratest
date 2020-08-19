<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\WrapperRunner;
use ParaTest\Tests\Functional\RunnerResult;
use ParaTest\Tests\TestBase;
use PHPUnit\TextUI\TestRunner;
use RuntimeException;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use Symfony\Component\Console\Output\BufferedOutput;

use function array_merge;
use function is_file;
use function is_string;
use function uniqid;
use function unlink;

/**
 * @requires OSFAMILY Linux
 * @covers \ParaTest\Runners\PHPUnit\BaseWrapperRunner
 * @covers \ParaTest\Runners\PHPUnit\WrapperRunner
 * @covers \ParaTest\Runners\PHPUnit\Worker\BaseWorker
 * @covers \ParaTest\Runners\PHPUnit\Worker\WrapperWorker
 */
final class WrapperRunnerTest extends TestBase
{
    /** @var array<string, string|bool|int> */
    private $bareOptions;

    protected function setUpTest(): void
    {
        $this->bareOptions = [
            '--tmp-dir' => TMP_DIR,
            '--coverage-php' => TMP_DIR . DS . uniqid('result_'),
        ];
    }

    private function runWrapperRunner(): RunnerResult
    {
        $output        = new BufferedOutput();
        $wrapperRunner = new WrapperRunner($this->createOptionsFromArgv($this->bareOptions), $output);
        $wrapperRunner->run();

        return new RunnerResult($wrapperRunner->getExitCode(), $output->fetch());
    }

    public function testWrapperRunnerNotAvailableInFunctionalMode(): void
    {
        $this->bareOptions['--path']       = $this->fixture('passing-tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--functional'] = true;

        $this->expectException(InvalidArgumentException::class);

        $this->runWrapperRunner();
    }

    public function testResultsAreCorrect(): void
    {
        $this->bareOptions['--path'] = $this->fixture('passing-tests' . DS . 'GroupsTest.php');

        $this->assertTestsPassed($this->runWrapperRunner());

        $coveragePhp = include $this->bareOptions['--coverage-php'];
        static::assertInstanceOf(CodeCoverage::class, $coveragePhp);
    }

    public function testRunningFewerTestsThanTheWorkersIsPossible(): void
    {
        $this->bareOptions['--path']      = $this->fixture('passing-tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--processes'] = 2;

        $this->assertTestsPassed($this->runWrapperRunner());

        $coveragePhp = include $this->bareOptions['--coverage-php'];
        static::assertInstanceOf(CodeCoverage::class, $coveragePhp);
    }

    public function testExitCodes(): void
    {
        $this->bareOptions['--path'] = $this->fixture('wrapper-runner-exit-code-tests' . DS . 'ErrorTest.php');
        $runnerResult                = $this->runWrapperRunner();

        static::assertStringContainsString('Tests: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Failures: 0', $runnerResult->getOutput());
        static::assertStringContainsString('Errors: 1', $runnerResult->getOutput());
        static::assertEquals(TestRunner::EXCEPTION_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--path'] = $this->fixture('wrapper-runner-exit-code-tests' . DS . 'FailureTest.php');
        $runnerResult                = $this->runWrapperRunner();

        static::assertStringContainsString('Tests: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Failures: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Errors: 0', $runnerResult->getOutput());
        static::assertEquals(TestRunner::FAILURE_EXIT, $runnerResult->getExitCode());

        $this->bareOptions['--path'] = $this->fixture('wrapper-runner-exit-code-tests' . DS . 'SuccessTest.php');
        $runnerResult                = $this->runWrapperRunner();

        static::assertStringContainsString('OK (1 test, 1 assertion)', $runnerResult->getOutput());
        static::assertEquals(TestRunner::SUCCESS_EXIT, $runnerResult->getExitCode());

        $file = $this->bareOptions['--coverage-php'];
        if (is_string($file) && is_file($file)) {
            unlink($file);
        }

        $this->bareOptions['--path'] = $this->fixture('wrapper-runner-exit-code-tests');
        $runnerResult                = $this->runWrapperRunner();

        static::assertStringContainsString('Tests: 3', $runnerResult->getOutput());
        static::assertStringContainsString('Failures: 1', $runnerResult->getOutput());
        static::assertStringContainsString('Errors: 1', $runnerResult->getOutput());
        static::assertEquals(TestRunner::EXCEPTION_EXIT, $runnerResult->getExitCode());

        $coveragePhp = include $this->bareOptions['--coverage-php'];
        static::assertInstanceOf(CodeCoverage::class, $coveragePhp);
    }

    public function testParallelSuiteOption(): void
    {
        $this->bareOptions = array_merge($this->bareOptions, [
            '--configuration' => $this->fixture('phpunit-parallel-suite.xml'),
            '--parallel-suite' => true,
            '--processes' => 2,
            '--verbose' => 1,
            '--whitelist' => $this->fixture('parallel-suite'),
        ]);

        $this->assertTestsPassed($this->runWrapperRunner());

        $coveragePhp = include $this->bareOptions['--coverage-php'];
        static::assertInstanceOf(CodeCoverage::class, $coveragePhp);
    }

    public function testRaiseExceptionWhenATestCallsExit(): void
    {
        $this->bareOptions['--path'] = $this->fixture('exit-tests' . DS . 'UnitTestThatExitsSilentlyTest.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/UnitTestThatExitsSilentlyTest/');

        $this->runWrapperRunner();
    }
}
