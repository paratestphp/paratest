<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use ParaTest\Console\Commands\ParaTestCommand;
use ParaTest\Console\Testers\PHPUnit;
use ParaTest\Runners\PHPUnit\EmptyRunnerStub;
use ParaTest\Runners\PHPUnit\Runner;
use ParaTest\Runners\PHPUnit\SqliteRunner;
use ParaTest\Runners\PHPUnit\WrapperRunner;
use ParseError;
use Symfony\Component\Console\Input\ArrayInput;

use function array_key_exists;
use function basename;
use function chdir;
use function dirname;
use function file_exists;
use function glob;
use function is_dir;
use function is_string;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class PHPUnitTest extends FunctionalTestBase
{
    public function testWithJustBootstrap(): void
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests'));
    }

    public function testWithJustConfiguration(): void
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests', ['configuration' => PHPUNIT_CONFIGURATION]));
    }

    /**
     * @dataProvider provideGithubIssues
     */
    public function testGithubIssues(string $directory): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            null,
            [
                'configuration' => sprintf('%s%sphpunit%s.xml', $directory, DS, basename($directory)),
            ],
            Runner::class
        ));
    }

    /**
     * @return array<string, string[]>
     */
    public function provideGithubIssues(): array
    {
        $directory = $this->fixture('github');
        $cases     = [];
        foreach (glob($directory . DS . '*') as $path) {
            if (! is_string($path) || ! is_dir($path)) {
                continue;
            }

            $cases['issue-' . basename($path)] = [$path];
        }

        return $cases;
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testWithWrapperRunner(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['configuration' => PHPUNIT_CONFIGURATION],
            WrapperRunner::class
        ));
    }

    public function testWithSqliteRunner(): void
    {
        $this->guardSqliteExtensionLoaded();

        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['configuration' => PHPUNIT_CONFIGURATION],
            SqliteRunner::class
        ));
    }

    public function testWithCustomRunner(): void
    {
        $runnerResult = $this->invokeParatest(
            'passing-tests',
            ['configuration' => PHPUNIT_CONFIGURATION],
            EmptyRunnerStub::class
        );
        static::assertStringContainsString(EmptyRunnerStub::OUTPUT, $runnerResult->getOutput());
    }

    public function testWithColorsGreenBar(): void
    {
        $proc = $this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            ['colors' => true]
        );
        static::assertStringContainsString(
            '[30;42m[2KOK',
            $proc->getOutput()
        );
    }

    public function testWithColorsRedBar(): void
    {
        $proc = $this->invokeParatest(
            'failing-tests/UnitTestWithErrorTest.php',
            ['colors' => true]
        );
        static::assertStringContainsString(
            '[37;41m[2KFAILURES',
            $proc->getOutput()
        );
    }

    public function testParatestEnvironmentVariable(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            []
        ));
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testParatestEnvironmentVariableWithWrapperRunner(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            [],
            WrapperRunner::class
        ));
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testParatestEnvironmentVariableWithWrapperRunnerWithoutTestTokens(): void
    {
        $proc = $this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            ['no-test-tokens' => true],
            WrapperRunner::class
        );
        static::assertMatchesRegularExpression('/Failures: 1/', $proc->getOutput());
    }

    public function testParatestEnvironmentVariableWithSqliteRunner(): void
    {
        $this->guardSqliteExtensionLoaded();
        $this->assertTestsPassed($this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            [],
            SqliteRunner::class
        ));
    }

    public function testWithConfigurationInDirWithoutConfigFile(): void
    {
        chdir(dirname(FIXTURES));
        $this->assertTestsPassed($this->invokeParatest('passing-tests'));
    }

    public function testFunctionalWithBootstrap(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['functional' => true]
        ));
    }

    public function testFunctionalWithConfiguration(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['configuration' => PHPUNIT_CONFIGURATION, 'functional' => true]
        ));
    }

    public function testWithBootstrapAndProcessesSwitch(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests',
            ['processes' => 6]
        );
        static::assertMatchesRegularExpression('/Running phpunit in 6 processes/', $proc->getOutput());
        $this->assertTestsPassed($proc);
    }

    public function testDefaultSettingsWithoutBootstrap(): void
    {
        chdir(PARATEST_ROOT);
        $this->assertTestsPassed($this->invokeParatest('passing-tests'));
    }

    public function testDefaultSettingsWithSpecifiedPath(): void
    {
        chdir(PARATEST_ROOT);
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['path' => 'test/fixtures/passing-tests']
        ));
    }

    public function testLoggingXmlOfDirectory(): void
    {
        chdir(PARATEST_ROOT);
        $output = FIXTURES . DS . 'logs' . DS . 'functional-directory.xml';
        $proc   = $this->invokeParatest('passing-tests', ['log-junit' => $output]);
        $this->assertTestsPassed($proc);
        static::assertFileExists($output);
        if (! file_exists($output)) {
            return;
        }

        unlink($output);
    }

    public function testTestTokenEnvVarIsPassed(): void
    {
        chdir(PARATEST_ROOT);
        $proc = $this->invokeParatest(
            'passing-tests',
            ['path' => 'test/fixtures/paratest-only-tests/TestTokenTest.php']
        );
        $this->assertTestsPassed($proc, '1', '1');
    }

    public function testLoggingXmlOfSingleFile(): void
    {
        chdir(PARATEST_ROOT);
        $output = FIXTURES . DS . 'logs' . DS . 'functional-file.xml';
        $proc   = $this->invokeParatest('passing-tests/GroupsTest.php', ['log-junit' => $output]);
        $this->assertTestsPassed($proc, '5', '5');
        static::assertFileExists($output);
        if (! file_exists($output)) {
            return;
        }

        unlink($output);
    }

    public function testSuccessfulRunHasExitCode0(): void
    {
        $proc = $this->invokeParatest('passing-tests/GroupsTest.php');
        static::assertEquals(0, $proc->getExitCode());
    }

    public function testFailedRunHasExitCode1(): void
    {
        $proc = $this->invokeParatest('failing-tests/FailingTest.php');
        static::assertEquals(1, $proc->getExitCode());
    }

    public function testRunWithErrorsHasExitCode2(): void
    {
        $proc = $this->invokeParatest('failing-tests/UnitTestWithErrorTest.php');
        static::assertEquals(2, $proc->getExitCode());
    }

    /**
     * Paratest itself will throw a catchable exception while parsing the unit test before even opening a process and
     * running it. In some PHP/library versions, the exception code would be 255. Otherwise, the exception code was 0
     * and is manually converted to 1 inside the Symfony Console runner.
     */
    public function testRunWithFatalParseErrorsHasExitCode255or1(): void
    {
        self::expectException(ParseError::class);

        $this->invokeParatest('fatal-tests/UnitTestWithFatalParseErrorTest.php');
    }

    public function testStopOnFailurePreventsStartingFurtherTestsAfterFailure(): void
    {
        $proc    = $this->invokeParatest('failing-tests/StopOnFailureTest.php', [
            'stop-on-failure' => true,
            'functional' => true,
            'processes' => '1',
        ]);
        $results = $proc->getOutput();
        static::assertStringContainsString('Tests: 2,', $results);     // The suite actually has 4 tests
        static::assertStringContainsString('Failures: 1,', $results);  // The suite actually has 2 failing tests
    }

    public function testFullyConfiguredRun(): void
    {
        $output = FIXTURES . DS . 'logs' . DS . 'functional.xml';
        $proc   = $this->invokeParatest('passing-tests', [
            'functional' => true,
            'processes' => '6',
            'log-junit' => $output,
        ]);
        $this->assertTestsPassed($proc);
        $results = $proc->getOutput();
        static::assertMatchesRegularExpression('/Running phpunit in 6 processes/', $results);
        static::assertMatchesRegularExpression('/Functional mode is on/i', $results);
        static::assertFileExists($output);
        if (! file_exists($output)) {
            return;
        }

        unlink($output);
    }

    public function testUsingDefaultLoadedConfiguration(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['functional' => true]
        ));
    }

    public function testEachTestRunsExactlyOnceOnChainDependencyOnFunctionalMode(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests/DependsOnChain.php',
            ['functional' => true]
        );
        $this->assertTestsPassed($proc, '5', '5');
    }

    public function testEachTestRunsExactlyOnceOnSameDependencyOnFunctionalMode(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests/DependsOnSame.php',
            ['functional' => true]
        );
        $this->assertTestsPassed($proc, '3', '3');
    }

    public function testFunctionalModeEachTestCalledOnce(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests/FunctionalModeEachTestCalledOnce.php',
            ['functional' => true]
        );
        $this->assertTestsPassed($proc, '2', '2');
    }

    /**
     * @return array<int, array<int, string|array<string, string>>>
     */
    public function setsCoveragePhpDataProvider(): array
    {
        return [
            [
                ['coverage-html' => 'wayne'],
                '',
            ],
            [
                ['coverage-clover' => 'wayne'],
                '',
            ],
            [
                ['coverage-php' => 'notWayne'],
                'notWayne',
            ],
            [
                ['coverage-clover' => 'wayne', 'coverage-php' => 'notWayne'],
                'notWayne',
            ],
        ];
    }

    /**
     * @param array<string, string> $options
     *
     * @dataProvider setsCoveragePhpDataProvider
     */
    public function testSetsCoveragePhp(array $options, string $coveragePhp): void
    {
        $phpUnit = new PHPUnit();
        $c       = new ParaTestCommand($phpUnit);

        $input = new ArrayInput([], $c->getDefinition());
        foreach ($options as $key => $value) {
            $input->setOption($key, $value);
        }

        $input->setArgument('path', '.');
        $options = $phpUnit->getRunnerOptions($input);

        if ($coveragePhp !== '') {
            static::assertEquals($coveragePhp, $options['coverage-php']);
        } else {
            $dir = tempnam(sys_get_temp_dir(), 'paratest_');
            static::assertStringStartsWith(dirname($dir), $options['coverage-php']);
        }
    }

    /**
     * @param array<string, array<string, int|string>> $options
     * @param array<string, array<string, int|string>> $expected
     *
     * @dataProvider getRunnerOptionsDataProvider
     */
    public function testGetRunnerOptions(array $options, array $expected): void
    {
        $phpUnit = new PHPUnit();
        $c       = new ParaTestCommand($phpUnit);
        $input   = new ArrayInput($options, $c->getDefinition());

        $options = $phpUnit->getRunnerOptions($input);

        // Note:
        // 'coverage-php' contains a random, temporary string.
        // has to be refactored to be testable but I'll leave that as a
        // TODO
        if (array_key_exists('coverage-php', $options)) {
            unset($options['coverage-php']);
        }

        static::assertEquals($expected, $options);
    }

    /**
     * @return array<string, array<string, array<string, array<int, string>|int|string>>>
     */
    public function getRunnerOptionsDataProvider(): array
    {
        return [
            'default' => [
                'input' => [
                    'path' => 'bar',
                    '--processes' => '10',
                ],
                'expected' => [
                    'path' => 'bar',
                    'processes' => '10',
                ],
            ],
            'accepts all defined options' => [
                'input' => [
                    'path' => 'bar',
                    '--processes' => '10',
                    '--functional' => 1,
                    '--no-test-tokens' => 1,
//                    '--help' => "",
                    '--coverage-clover' => 'clover',
                    '--coverage-crap4j' => 'xml',
                    '--coverage-html' => 'html',
                    '--coverage-text' => 'text',
                    '--coverage-xml' => 'xml',
                    '--max-batch-size' => '5',
                    '--filter' => 'filter',
                    '--parallel-suite' => 'parallel-suite',
                ],
                'expected' => [
                    'path' => 'bar',
                    'processes' => '10',
                    'functional' => 1,
                    'no-test-tokens' => 1,
                    'coverage-clover' => 'clover',
                    'coverage-crap4j' => 'xml',
                    'coverage-html' => 'html',
                    'coverage-text' => 'text',
                    'coverage-xml' => 'xml',
                    'max-batch-size' => '5',
                    'filter' => 'filter',
                    'parallel-suite' => 'parallel-suite',
                ],
            ],
            "splits testsuite on ','" => [
                'input' => [
                    'path' => 'bar',
                    '--processes' => '10',
                    '--testsuite' => 't1,t2',
                ],
                'expected' => [
                    'path' => 'bar',
                    'processes' => '10',
                    'testsuite' => [
                        't1',
                        't2',
                    ],
                ],
            ],
        ];
    }
}
