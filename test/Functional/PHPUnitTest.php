<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use ParaTest\Console\Commands\ParaTestCommand;
use ParaTest\Console\Testers\PHPUnit;
use Symfony\Component\Console\Input\ArrayInput;

class PHPUnitTest extends FunctionalTestBase
{
    public function testWithJustBootstrap()
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests', [
            'bootstrap' => BOOTSTRAP,
        ]));
    }

    public function testWithBootstrapThatDoesNotExist()
    {
        $bootstrap = '/fileThatDoesNotExist.php';
        $proc = $this->invokeParatest('passing-tests', ['bootstrap' => $bootstrap]);
        $errors = $proc->getErrorOutput();
        $this->assertEquals(1, $proc->getExitCode(), 'Unexpected exit code');

        // The [RuntimeException] message appears only on lower 6.x versions of Phpunit
        $this->assertRegExp(
            '/(\[RuntimeException\]|Bootstrap specified but could not be found)/',
            $errors,
            'Expected exception name not found in output'
        );
    }

    public function testWithJustConfiguration()
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests', [
            'configuration' => PHPUNIT_CONFIGURATION,
        ]));
    }

    public function testWithWrapperRunner()
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests', [
            'configuration' => PHPUNIT_CONFIGURATION,
            'runner' => 'WrapperRunner',
        ]));
    }

    public function testWithSqliteRunner()
    {
        $this->guardSqliteExtensionLoaded();

        $this->assertTestsPassed($this->invokeParatest('passing-tests', [
            'configuration' => PHPUNIT_CONFIGURATION,
            'runner' => 'SqliteRunner',
        ]));
    }

    public function testWithCustomRunner()
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
        $this->assertEquals('EXECUTED', $cb->getBuffer());
    }

    public function testWithColorsGreenBar()
    {
        $proc = $this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            ['bootstrap' => BOOTSTRAP, 'colors']
        );
        $this->assertStringContainsString(
            '[30;42m[2KOK',
            $proc->getOutput()
        );
    }

    public function testWithColorsRedBar()
    {
        $proc = $this->invokeParatest(
            'failing-tests/UnitTestWithErrorTest.php',
            ['bootstrap' => BOOTSTRAP, 'colors']
        );
        $this->assertStringContainsString(
            '[37;41m[2KFAILURES',
            $proc->getOutput()
        );
    }

    public function testParatestEnvironmentVariable()
    {
        $this->assertTestsPassed($this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            ['bootstrap' => BOOTSTRAP]
        ));
    }

    public function testParatestEnvironmentVariableWithWrapperRunner()
    {
        $this->assertTestsPassed($this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            ['bootstrap' => BOOTSTRAP, 'runner' => 'WrapperRunner']
        ));
    }

    public function testParatestEnvironmentVariableWithWrapperRunnerWithoutTestTokens()
    {
        $proc = $this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            ['bootstrap' => BOOTSTRAP, 'runner' => 'WrapperRunner', 'no-test-tokens' => 0]
        );
        $this->assertRegexp('/Failures: 1/', $proc->getOutput());
    }

    public function testParatestEnvironmentVariableWithSqliteRunner()
    {
        $this->guardSqliteExtensionLoaded();
        $this->assertTestsPassed($this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            ['bootstrap' => BOOTSTRAP, 'runner' => 'SqliteRunner']
        ));
    }

    public function testWithConfigurationInDirWithoutConfigFile()
    {
        chdir(dirname(FIXTURES));
        $this->assertTestsPassed($this->invokeParatest('passing-tests'));
    }

    public function testWithConfigurationThatDoesNotExist()
    {
        $proc = $this->invokeParatest(
            'passing-tests',
            ['configuration' => FIXTURES . DS . 'phpunit.xml.disto']
        ); // dist"o" does not exist
        $this->assertRegExp('/Could not read ".*phpunit.xml.disto"./', $proc->getOutput());
    }

    public function testFunctionalWithBootstrap()
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['bootstrap' => BOOTSTRAP, 'functional']
        ));
    }

    public function testFunctionalWithConfiguration()
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['configuration' => PHPUNIT_CONFIGURATION, 'functional']
        ));
    }

    public function testWithBootstrapAndProcessesSwitch()
    {
        $proc = $this->invokeParatest(
            'passing-tests',
            ['bootstrap' => BOOTSTRAP, 'processes' => 6]
        );
        $this->assertRegExp('/Running phpunit in 6 processes/', $proc->getOutput());
        $this->assertTestsPassed($proc);
    }

    public function testWithBootstrapAndManuallySpecifiedPHPUnit()
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['bootstrap' => BOOTSTRAP, 'phpunit' => PHPUNIT]
        ));
    }

    public function testDefaultSettingsWithoutBootstrap()
    {
        chdir(PARATEST_ROOT);
        $this->assertTestsPassed($this->invokeParatest('passing-tests'));
    }

    public function testDefaultSettingsWithSpecifiedPath()
    {
        chdir(PARATEST_ROOT);
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['path' => 'test/fixtures/passing-tests']
        ));
    }

    public function testLoggingXmlOfDirectory()
    {
        chdir(PARATEST_ROOT);
        $output = FIXTURES . DS . 'logs' . DS . 'functional-directory.xml';
        $proc = $this->invokeParatest('passing-tests', [
            'log-junit' => $output,
        ]);
        $this->assertTestsPassed($proc);
        $this->assertFileExists($output);
        if (file_exists($output)) {
            unlink($output);
        }
    }

    public function testTestTokenEnvVarIsPassed()
    {
        chdir(PARATEST_ROOT);
        $proc = $this->invokeParatest(
            'passing-tests',
            ['path' => 'test/fixtures/paratest-only-tests/TestTokenTest.php']
        );
        $this->assertTestsPassed($proc, 1, 1);
    }

    public function testLoggingXmlOfSingleFile()
    {
        chdir(PARATEST_ROOT);
        $output = FIXTURES . DS . 'logs' . DS . 'functional-file.xml';
        $proc = $this->invokeParatest('passing-tests/GroupsTest.php', [
            'log-junit' => $output,
            'bootstrap' => BOOTSTRAP,
        ]);
        $this->assertTestsPassed($proc, 5, 5);
        $this->assertFileExists($output);
        if (file_exists($output)) {
            unlink($output);
        }
    }

    public function testSuccessfulRunHasExitCode0()
    {
        $proc = $this->invokeParatest('passing-tests/GroupsTest.php', [
            'bootstrap' => BOOTSTRAP,
        ]);
        $this->assertEquals(0, $proc->getExitCode());
    }

    public function testFailedRunHasExitCode1()
    {
        $proc = $this->invokeParatest('failing-tests/FailingTest.php', [
            'bootstrap' => BOOTSTRAP,
        ]);
        $this->assertEquals(1, $proc->getExitCode());
    }

    public function testRunWithErrorsHasExitCode2()
    {
        $proc = $this->invokeParatest('failing-tests/UnitTestWithErrorTest.php', [
            'bootstrap' => BOOTSTRAP,
        ]);
        $this->assertEquals(2, $proc->getExitCode());
    }

    /**
     * Paratest itself will throw a catchable exception while parsing the unit test before even opening a process and
     * running it. In some PHP/library versions, the exception code would be 255. Otherwise, the exception code was 0
     * and is manually converted to 1 inside the Symfony Console runner.
     */
    public function testRunWithFatalParseErrorsHasExitCode255or1()
    {
        $proc = $this->invokeParatest('fatal-tests/UnitTestWithFatalParseErrorTest.php', [
            'bootstrap' => BOOTSTRAP,
        ]);
        $this->assertContains($proc->getExitCode(), [1, 255]);
    }

    public function testStopOnFailurePreventsStartingFurtherTestsAfterFailure()
    {
        $proc = $this->invokeParatest('failing-tests/StopOnFailureTest.php', [
            'bootstrap' => BOOTSTRAP,
            'stop-on-failure' => '',
            'f' => '',
            'p' => '1',
        ]);
        $results = $proc->getOutput();
        $this->assertStringContainsString('Tests: 2,', $results);     // The suite actually has 4 tests
        $this->assertStringContainsString('Failures: 1,', $results);  // The suite actually has 2 failing tests
    }

    public function testFullyConfiguredRun()
    {
        $output = FIXTURES . DS . 'logs' . DS . 'functional.xml';
        $proc = $this->invokeParatest('passing-tests', [
            'bootstrap' => BOOTSTRAP,
            'phpunit' => PHPUNIT,
            'f' => '',
            'p' => '6',
            'log-junit' => $output,
        ]);
        $this->assertTestsPassed($proc);
        $results = $proc->getOutput();
        $this->assertRegExp('/Running phpunit in 6 processes/', $results);
        $this->assertRegExp('/Functional mode is on/i', $results);
        $this->assertFileExists($output);
        if (file_exists($output)) {
            unlink($output);
        }
    }

    public function testUsingDefaultLoadedConfiguration()
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['functional']
        ));
    }

    public function testEachTestRunsExactlyOnceOnChainDependencyOnFunctionalMode()
    {
        $proc = $this->invokeParatest(
            'passing-tests/DependsOnChain.php',
            ['bootstrap' => BOOTSTRAP, 'functional']
        );
        $this->assertTestsPassed($proc, 5, 5);
    }

    public function testEachTestRunsExactlyOnceOnSameDependencyOnFunctionalMode()
    {
        $proc = $this->invokeParatest(
            'passing-tests/DependsOnSame.php',
            ['bootstrap' => BOOTSTRAP, 'functional']
        );
        $this->assertTestsPassed($proc, 3, 3);
    }

    public function testFunctionalModeEachTestCalledOnce()
    {
        $proc = $this->invokeParatest(
            'passing-tests/FunctionalModeEachTestCalledOnce.php',
            ['bootstrap' => BOOTSTRAP, 'functional']
        );
        $this->assertTestsPassed($proc, 2, 2);
    }

    public function setsCoveragePhpDataProvider()
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
     * @dataProvider setsCoveragePhpDataProvider
     *
     * @param $options
     * @param $coveragePhp
     */
    public function testSetsCoveragePhp($options, $coveragePhp)
    {
        $phpUnit = new \ParaTest\Console\Testers\PHPUnit();
        $c = new \ParaTest\Console\Commands\ParaTestCommand($phpUnit);

        $input = new \Symfony\Component\Console\Input\ArrayInput([], $c->getDefinition());
        foreach ($options as $key => $value) {
            $input->setOption($key, $value);
        }
        $input->setArgument('path', '.');
        $options = $phpUnit->getRunnerOptions($input);

        if ($coveragePhp) {
            $this->assertEquals($coveragePhp, $options['coverage-php']);
        } else {
            $dir = tempnam(sys_get_temp_dir(), 'paratest_');
            $this->assertStringStartsWith(dirname($dir), $options['coverage-php']);
        }
    }

    /**
     * @dataProvider getRunnerOptionsDataProvider
     *
     * @param array $options
     * @param array $expected
     */
    public function testGetRunnerOptions(array $options, array $expected)
    {
        $phpUnit = new PHPUnit();
        $c = new ParaTestCommand($phpUnit);
        $input = new ArrayInput($options, $c->getDefinition());

        $options = $phpUnit->getRunnerOptions($input);

        // Note:
        // 'coverage-php' contains a random, temporary string.
        // has to be refactored to be testable but I'll leave that as a
        // TODO
        if (array_key_exists('coverage-php', $options)) {
            unset($options['coverage-php']);
        }

        $this->assertEquals($expected, $options);
    }

    public function getRunnerOptionsDataProvider()
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
