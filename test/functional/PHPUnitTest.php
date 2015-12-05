<?php

class PHPUnitTest extends FunctionalTestBase
{
    public function testWithJustBootstrap()
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests', array(
            'bootstrap' => BOOTSTRAP
        )));
    }

    public function testWithBootstrapThatDoesNotExist()
    {
        $bootstrap = '/fileThatDoesNotExist.php';
        $proc = $this->invokeParatest('passing-tests', array('bootstrap' => $bootstrap));
        $errors = $proc->getErrorOutput();
        $this->assertEquals(1, $proc->getExitCode(), 'Unexpected exit code');
        $this->assertContains('[RuntimeException]', $errors, 'Expected exception name not found in output');
        $this->assertContains(sprintf('Bootstrap specified but could not be found (%s)', $bootstrap), $errors, 'Expected error message not found in output');
    }

    public function testWithJustConfiguration()
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests', array(
            'configuration' => PHPUNIT_CONFIGURATION
        )));
    }

    public function testWithWrapperRunner()
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests', array(
            'configuration' => PHPUNIT_CONFIGURATION,
            'runner' => 'WrapperRunner'
        )));
    }

    public function testWithCustomRunner()
    {
        $cb = new ProcessCallback();

        $this->invokeParatest(
            'passing-tests',
            array(
                'configuration' => PHPUNIT_CONFIGURATION,
                'runner'        => 'EmptyRunnerStub'
            ),
            array($cb, 'callback')
        );
        $this->assertEquals('EXECUTED', $cb->getBuffer());
    }

    public function testWithColorsGreenBar()
    {
        $proc = $this->invokeParatest('paratest-only-tests/EnvironmentTest.php',
            array('bootstrap' => BOOTSTRAP, 'colors'));
        $this->assertContains(
            "[30;42m[2KOK",
            $proc->getOutput()
        );
    }

    public function testWithColorsRedBar()
    {
        $proc = $this->invokeParatest('failing-tests/UnitTestWithErrorTest.php',
            array('bootstrap' => BOOTSTRAP, 'colors'));
        $this->assertContains(
            "[37;41m[2KFAILURES",
            $proc->getOutput()
        );
    }

    public function testParatestEnvironmentVariable()
    {
        $this->assertTestsPassed($this->invokeParatest('paratest-only-tests/EnvironmentTest.php',
            array('bootstrap' => BOOTSTRAP)
        ));
    }

    public function testParatestEnvironmentVariableWithWrapperRunner()
    {
        $this->assertTestsPassed($this->invokeParatest('paratest-only-tests/EnvironmentTest.php',
            array('bootstrap' => BOOTSTRAP, 'runner' => 'WrapperRunner')
        ));
    }

    public function testParatestEnvironmentVariableWithWrapperRunnerandWithoutTestTokens()
    {
        $proc = $this->invokeParatest('paratest-only-tests/EnvironmentTest.php',
            array('bootstrap' => BOOTSTRAP, 'runner' => 'WrapperRunner', 'no-test-tokens' => 0));
        $this->assertRegexp('/Failures: 1/', $proc->getOutput());
    }

    public function testWithConfigurationInDirWithoutConfigFile()
    {
        chdir(dirname(FIXTURES));
        $this->assertTestsPassed($this->invokeParatest('passing-tests'));
    }

    public function testWithConfigurationThatDoesNotExist()
    {
        $proc = $this->invokeParatest('passing-tests',
            array('configuration' => FIXTURES . DS . 'phpunit.xml.disto'));
        $this->assertRegExp('/Could not read ".*phpunit.xml.disto"./', $proc->getOutput());
    }

    public function testFunctionalWithBootstrap()
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests',
            array('bootstrap' => BOOTSTRAP, 'functional')
        ));
    }

    public function testFunctionalWithConfiguration()
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests',
            array('configuration' => PHPUNIT_CONFIGURATION, 'functional')
        ));
    }

    public function testWithBootstrapAndProcessesSwitch()
    {
        $proc = $this->invokeParatest('passing-tests',
            array('bootstrap' => BOOTSTRAP, 'processes' => 6));
        $this->assertRegExp('/Running phpunit in 6 processes/', $proc->getOutput());
        $this->assertTestsPassed($proc);
    }

    public function testWithBootstrapAndManuallySpecifiedPHPUnit()
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests',
            array('bootstrap' => BOOTSTRAP, 'phpunit' => PHPUNIT)
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
        $this->assertTestsPassed($this->invokeParatest('passing-tests',
            array('path' => 'test/fixtures/passing-tests')
        ));
    }

    public function testLoggingXmlOfDirectory()
    {
        chdir(PARATEST_ROOT);
        $output = FIXTURES . DS . 'logs' . DS . 'functional-directory.xml';
        $proc = $this->invokeParatest('passing-tests', array(
            'log-junit' => $output
        ));
        $this->assertTestsPassed($proc);
        $this->assertTrue(file_exists($output));
        if(file_exists($output)) unlink($output);
    }

    public function testTestTokenEnvVarIsPassed()
    {
        chdir(PARATEST_ROOT);
        $proc = $this->invokeParatest('passing-tests',
            array('path' => 'test/fixtures/paratest-only-tests/TestTokenTest.php'));
        $this->assertTestsPassed($proc, 1, 1);
    }

    public function testLoggingXmlOfSingleFile()
    {
        chdir(PARATEST_ROOT);
        $output = FIXTURES . DS . 'logs' . DS . 'functional-file.xml';
        $proc = $this->invokeParatest('passing-tests/GroupsTest.php', array(
            'log-junit' => $output,
            'bootstrap' => BOOTSTRAP
        ));
        $this->assertTestsPassed($proc, 5, 5);
        $this->assertTrue(file_exists($output));
        if(file_exists($output)) unlink($output);
    }

    public function testSuccessfulRunHasExitCode0()
    {
        $proc = $this->invokeParatest('passing-tests/GroupsTest.php', array(
            'bootstrap' => BOOTSTRAP
        ));
        $this->assertEquals(0, $proc->getExitCode());
    }

    public function testFailedRunHasExitCode1()
    {
        $proc = $this->invokeParatest('failing-tests/FailingTest.php',array(
            'bootstrap' => BOOTSTRAP
        ));
        $this->assertEquals(1, $proc->getExitCode());
    }

    public function testRunWithErrorsHasExitCode2()
    {
        $proc = $this->invokeParatest('failing-tests/UnitTestWithErrorTest.php', array(
            'bootstrap' => BOOTSTRAP
        ));
        $this->assertEquals(2, $proc->getExitCode());
    }

    public function testRunWithFatalParseErrorsHasExitCode255()
    {
        $proc = $this->invokeParatest('fatal-tests/UnitTestWithFatalParseErrorTest.php', array(
            'bootstrap' => BOOTSTRAP
        ));
        $this->assertEquals(255, $proc->getExitCode());
    }

    public function testRunWithFatalRuntimeErrorsHasExitCode1()
    {
        if (PHP_VERSION_ID >= 70000) {
            $this->markTestSkipped('fatals are handled like normal exceptions with php7');
        }
        $proc = $this->invokeParatest('fatal-tests/UnitTestWithFatalFunctionErrorTest.php', array(
            'bootstrap' => BOOTSTRAP
        ));
        $this->assertEquals(1, $proc->getExitCode());
    }

    public function testRunWithFatalRuntimeErrorOutputsError()
    {
        if (PHP_VERSION_ID >= 70000) {
            $this->markTestSkipped('fatals are handled like normal exceptions with php7');
        }
        $proc = $this->invokeParatest('fatal-tests/UnitTestWithFatalFunctionErrorTest.php', array(
            'bootstrap' => BOOTSTRAP
        ));
        $this->assertContains('Call to undefined function inexistent', $proc->getErrorOutput());
    }

    public function testRunWithFatalRuntimeErrorWithTheWrapperRunnerOutputsError()
    {
        if (PHP_VERSION_ID >= 70000) {
            $this->markTestSkipped('fatals are handled like normal exceptions with php7');
        }
        $proc = $this->invokeParatest('fatal-tests/UnitTestWithFatalFunctionErrorTest.php', array(
            'bootstrap' => BOOTSTRAP,
            'runner' => 'WrapperRunner'
        ));
        $this->assertContains('Call to undefined function inexistent', $proc->getErrorOutput());
    }

    public function testStopOnFailurePreventsStartingFurtherTestsAfterFailure()
    {
        $proc = $this->invokeParatest('failing-tests/StopOnFailureTest.php', array(
            'bootstrap' => BOOTSTRAP,
            'stop-on-failure' => '',
            'f' => '',
            'p' => '1'
        ));
        $results = $proc->getOutput();
        $this->assertContains('Tests: 2,', $results);     // The suite actually has 4 tests
        $this->assertContains('Failures: 1,', $results);  // The suite actually has 2 failing tests
    }

    public function testFullyConfiguredRun()
    {
        $output = FIXTURES . DS . 'logs' . DS . 'functional.xml';
        $proc = $this->invokeParatest('passing-tests', array(
            'bootstrap' => BOOTSTRAP,
            'phpunit' => PHPUNIT,
            'f' => '',
            'p' => '6',
            'log-junit' => $output
        ));
        $this->assertTestsPassed($proc);
        $results = $proc->getOutput();
        $this->assertRegExp('/Running phpunit in 6 processes/', $results);
        $this->assertRegExp('/Functional mode is on/i', $results);
        $this->assertTrue(file_exists($output));
        if(file_exists($output)) unlink($output);
    }

    public function testUsingDefaultLoadedConfiguration()
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests',
            array('functional')
        ));
    }

    public function testEachTestRunsExactlyOnceOnChainDependencyOnFunctionalMode()
    {
        $proc = $this->invokeParatest('passing-tests/DependsOnChain.php',
            array('bootstrap' => BOOTSTRAP, 'functional')
        );
        $this->assertTestsPassed($proc, 5, 5);
    }

    public function testEachTestRunsExactlyOnceOnSameDependencyOnFunctionalMode()
    {
        $proc = $this->invokeParatest('passing-tests/DependsOnSame.php',
            array('bootstrap' => BOOTSTRAP, 'functional')
        );
        $this->assertTestsPassed($proc, 3, 3);
    }

    public function testFunctionalModeEachTestCalledOnce()
    {
        $proc = $this->invokeParatest("passing-tests/FunctionalModeEachTestCalledOnce.php",
            array('bootstrap' => BOOTSTRAP, 'functional')
        );
        $this->assertTestsPassed($proc, 2, 2);
    }

    public function testTestsWithWarningsResultInFailure()
    {
        $proc = $this->invokeParatest("warning-tests/HasWarningsTest.php",
            array('bootstrap' => BOOTSTRAP)
        );

        $proc->getOutput();

        $this->assertEquals(1, $proc->getExitCode(), "Test should fail with 1");
    }

    public function setsCoveragePhpDataProvider()
    {
        return array(
            array(
                array('coverage-html' => 'wayne'),
                sys_get_temp_dir() . '/will_be_overwritten.php'
            ),
            array(
                array('coverage-clover' => 'wayne'),
                sys_get_temp_dir() . '/will_be_overwritten.php'
            ),
            array(
                array('coverage-php' => 'notWayne'),
                'notWayne'
            ),
            array(
                array('coverage-clover' => 'wayne', 'coverage-php' => 'notWayne'),
                'notWayne'
            )
        );
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

        $input = new \Symfony\Component\Console\Input\ArrayInput(array(), $c->getDefinition());
        foreach ($options as $key => $value) {
            $input->setOption($key, $value);
        }
        $input->setArgument('path', '.');
        $options = $phpUnit->getRunnerOptions($input);

        $this->assertEquals($coveragePhp, $options['coverage-php']);
    }
}
