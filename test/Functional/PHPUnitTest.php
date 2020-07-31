<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use ParaTest\Console\Commands\ParaTestCommand;
use ParaTest\Console\Testers\PHPUnit;
use Symfony\Component\Console\Input\ArrayInput;

use function array_key_exists;
use function chdir;
use function dirname;
use function file_exists;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class PHPUnitTest extends FunctionalTestBase
{
    public function testWithJustBootstrap(): void
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests', ['bootstrap' => BOOTSTRAP]));
    }

    public function testWithBootstrapThatDoesNotExist(): void
    {
        $bootstrap = '/fileThatDoesNotExist.php';
        $proc      = $this->invokeParatest('passing-tests', ['bootstrap' => $bootstrap]);
        $errors    = $proc->getErrorOutput();
        static::assertEquals(1, $proc->getExitCode(), 'Unexpected exit code');

        // The [RuntimeException] message appears only on lower 6.x versions of Phpunit
        static::assertMatchesRegularExpression(
            '/(\[RuntimeException\]|Bootstrap specified but could not be found)/',
            $errors,
            'Expected exception name not found in output'
        );
    }

    public function testWithJustConfiguration(): void
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests', ['configuration' => PHPUNIT_CONFIGURATION]));
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testWithWrapperRunner(): void
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests', [
            'configuration' => PHPUNIT_CONFIGURATION,
            'runner' => 'WrapperRunner',
        ]));
    }

    public function testWithSqliteRunner(): void
    {
        $this->guardSqliteExtensionLoaded();

        $this->assertTestsPassed($this->invokeParatest('passing-tests', [
            'configuration' => PHPUNIT_CONFIGURATION,
            'runner' => 'SqliteRunner',
        ]));
    }

    public function testWithCustomRunner(): void
    {
        $cb = new ProcessCallback();

        $this->invokeParatest(
            'passing-tests',
            [
                'configuration' => PHPUNIT_CONFIGURATION,
                'runner' => 'EmptyRunnerStub',
            ],
            [$cb, 'callback']
        );
        static::assertEquals('EXECUTED', $cb->getBuffer());
    }

    public function testWithColorsGreenBar(): void
    {
        $proc = $this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            ['bootstrap' => BOOTSTRAP, 'colors']
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
            ['bootstrap' => BOOTSTRAP, 'colors']
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
            ['bootstrap' => BOOTSTRAP]
        ));
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testParatestEnvironmentVariableWithWrapperRunner(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            ['bootstrap' => BOOTSTRAP, 'runner' => 'WrapperRunner']
        ));
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testParatestEnvironmentVariableWithWrapperRunnerWithoutTestTokens(): void
    {
        $proc = $this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            ['bootstrap' => BOOTSTRAP, 'runner' => 'WrapperRunner', 'no-test-tokens']
        );
        static::assertMatchesRegularExpression('/Failures: 1/', $proc->getOutput());
    }

    public function testParatestEnvironmentVariableWithSqliteRunner(): void
    {
        $this->guardSqliteExtensionLoaded();
        $this->assertTestsPassed($this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            ['bootstrap' => BOOTSTRAP, 'runner' => 'SqliteRunner']
        ));
    }

    public function testWithConfigurationInDirWithoutConfigFile(): void
    {
        chdir(dirname(FIXTURES));
        $this->assertTestsPassed($this->invokeParatest('passing-tests'));
    }

    public function testWithConfigurationThatDoesNotExist(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests',
            ['configuration' => FIXTURES . DS . 'phpunit.xml.disto']
        ); // dist"o" does not exist
        static::assertMatchesRegularExpression('/Could not read ".*phpunit.xml.disto"./', $proc->getOutput());
    }

    public function testFunctionalWithBootstrap(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['bootstrap' => BOOTSTRAP, 'functional']
        ));
    }

    public function testFunctionalWithConfiguration(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['configuration' => PHPUNIT_CONFIGURATION, 'functional']
        ));
    }

    public function testWithBootstrapAndProcessesSwitch(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests',
            ['bootstrap' => BOOTSTRAP, 'processes' => 6]
        );
        static::assertMatchesRegularExpression('/Running phpunit in 6 processes/', $proc->getOutput());
        $this->assertTestsPassed($proc);
    }

    public function testWithBootstrapAndManuallySpecifiedPHPUnit(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['bootstrap' => BOOTSTRAP, 'phpunit' => PHPUNIT]
        ));
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
        $proc   = $this->invokeParatest('passing-tests/GroupsTest.php', [
            'log-junit' => $output,
            'bootstrap' => BOOTSTRAP,
        ]);
        $this->assertTestsPassed($proc, '5', '5');
        static::assertFileExists($output);
        if (! file_exists($output)) {
            return;
        }

        unlink($output);
    }

    public function testSuccessfulRunHasExitCode0(): void
    {
        $proc = $this->invokeParatest('passing-tests/GroupsTest.php', ['bootstrap' => BOOTSTRAP]);
        static::assertEquals(0, $proc->getExitCode());
    }

    public function testFailedRunHasExitCode1(): void
    {
        $proc = $this->invokeParatest('failing-tests/FailingTest.php', ['bootstrap' => BOOTSTRAP]);
        static::assertEquals(1, $proc->getExitCode());
    }

    public function testRunWithErrorsHasExitCode2(): void
    {
        $proc = $this->invokeParatest('failing-tests/UnitTestWithErrorTest.php', ['bootstrap' => BOOTSTRAP]);
        static::assertEquals(2, $proc->getExitCode());
    }

    /**
     * Paratest itself will throw a catchable exception while parsing the unit test before even opening a process and
     * running it. In some PHP/library versions, the exception code would be 255. Otherwise, the exception code was 0
     * and is manually converted to 1 inside the Symfony Console runner.
     */
    public function testRunWithFatalParseErrorsHasExitCode255or1(): void
    {
        $proc = $this->invokeParatest('fatal-tests/UnitTestWithFatalParseErrorTest.php', ['bootstrap' => BOOTSTRAP]);
        static::assertContains($proc->getExitCode(), [1, 255]);
    }

    public function testStopOnFailurePreventsStartingFurtherTestsAfterFailure(): void
    {
        $proc    = $this->invokeParatest('failing-tests/StopOnFailureTest.php', [
            'bootstrap' => BOOTSTRAP,
            'stop-on-failure' => null,
            'f' => null,
            'p' => '1',
        ]);
        $results = $proc->getOutput();
        static::assertStringContainsString('Tests: 2,', $results);     // The suite actually has 4 tests
        static::assertStringContainsString('Failures: 1,', $results);  // The suite actually has 2 failing tests
    }

    public function testFullyConfiguredRun(): void
    {
        $output = FIXTURES . DS . 'logs' . DS . 'functional.xml';
        $proc   = $this->invokeParatest('passing-tests', [
            'bootstrap' => BOOTSTRAP,
            'phpunit' => PHPUNIT,
            'f' => null,
            'p' => '6',
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
            ['functional']
        ));
    }

    public function testEachTestRunsExactlyOnceOnChainDependencyOnFunctionalMode(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests/DependsOnChain.php',
            ['bootstrap' => BOOTSTRAP, 'functional']
        );
        $this->assertTestsPassed($proc, '5', '5');
    }

    public function testEachTestRunsExactlyOnceOnSameDependencyOnFunctionalMode(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests/DependsOnSame.php',
            ['bootstrap' => BOOTSTRAP, 'functional']
        );
        $this->assertTestsPassed($proc, '3', '3');
    }

    public function testFunctionalModeEachTestCalledOnce(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests/FunctionalModeEachTestCalledOnce.php',
            ['bootstrap' => BOOTSTRAP, 'functional']
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
