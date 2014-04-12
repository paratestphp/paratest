<?php

class PHPUnitTest extends FunctionalTestBase
{
    protected $path;

    public function setUp()
    {
        $this->path = FIXTURES . DS . 'tests';
        chdir($this->path);
    }

    public function testWithJustBootstrap()
    {
        $results = $this->paratest(array('bootstrap' => BOOTSTRAP));
        $this->assertResults($results);
    }

    public function testWithBootstrapThatDoesNotExist()
    {
        $bootstrap = '/fileThatDoesNotExist.php';

        $this->paratest(array('bootstrap' => $bootstrap));
        $errors = $this->getErrorOutput();
        $this->assertEquals(1, $this->getExitCode(), 'Unexpected exit code');
        $this->assertContains('[RuntimeException]', $errors, 'Expected exception name not found in output');
        $this->assertContains(sprintf('Bootstrap specified but could not be found (%s)', $bootstrap), $errors, 'Expected error message not found in output');
    }

    public function testWithJustConfiguration()
    {
        $results = $this->paratest(array('configuration' => PHPUNIT_CONFIGURATION));
        $this->assertResults($results);
    }

    public function testWithWrapperRunner()
    {
        $results = $this->paratest(array('configuration' => PHPUNIT_CONFIGURATION, 'runner' => 'WrapperRunner'));
        $this->assertResults($results);
    }

    public function testWithColorsGreenBar()
    {
        $this->path .= '/EnvironmentTest.php';

        $results = $this->paratest(array('bootstrap' => BOOTSTRAP), array('colors'));

        $this->assertContains(
            "[30;42m[2KOK",
            $results
        );
    }

    public function testWithColorsRedBar()
    {
        $this->path .= '/UnitTestWithErrorTest.php';

        $results = $this->paratest(array('bootstrap' => BOOTSTRAP), array('colors'));
        $this->assertContains(
            "[37;41m[2KFAILURES",
            $results
        );
    }

    public function testParatestEnvironmentVariable()
    {
        $this->path .= '/EnvironmentTest.php';
        $results = $this->paratest(array('bootstrap' => BOOTSTRAP));
        $this->assertRegexp('/OK \(\d+ test/', $results);
    }

    public function testParatestEnvironmentVariableWithWrapperRunner()
    {
        $this->path .= '/EnvironmentTest.php';
        $results = $this->paratest(array('bootstrap' => BOOTSTRAP, 'runner' => 'WrapperRunner'));
        $this->assertRegexp('/OK \(\d+ test/', $results);
    }

    public function testParatestEnvironmentVariableWithWrapperRunnerandWithoutTestTokens()
    {
        $this->path .= '/EnvironmentTest.php';
        $results = $this->paratest(array('bootstrap' => BOOTSTRAP, 'runner' => 'WrapperRunner', 'no-test-tokens' => 0));
        $this->assertRegexp('/Failures: 1/', $results);
    }

    public function testWithConfigurationInDirWithoutConfigFile()
    {
        chdir(dirname(FIXTURES));
        $this->path = '';
        $results = $this->paratest(array('configuration' => FIXTURES . DS . 'phpunit.xml.dist'));
        $this->assertResults($results);
    }

    public function testWithConfigurationThatDoesNotExist()
    {
        chdir(dirname(FIXTURES));
        $this->path = '';
        $results = $this->paratest(array('configuration' => FIXTURES . DS . 'phpunit.xml.disto'));
        $this->assertRegExp('/Could not read ".*phpunit.xml.disto"./', $results);
    }

    public function testFunctionalWithBootstrap()
    {
        $results = $this->paratest(array('bootstrap' => BOOTSTRAP, 'functional' => ''));
        $this->assertResults($results);
    }

    public function testFunctionalWithConfiguration()
    {
        $results = $this->paratest(array('configuration' => PHPUNIT_CONFIGURATION, 'functional' => ''));
        $this->assertResults($results);
    }

    public function testFunctionalWithBootstrapUsingShortOption()
    {
        $results = $this->paratest(array('bootstrap' => BOOTSTRAP, 'f' => ''));
        $this->assertResults($results);
    }

    public function testWithBootstrapAndProcessesSwitch()
    {
        $results = $this->paratest(array('bootstrap' => BOOTSTRAP, 'processes' => 6));
        $this->assertRegExp('/Running phpunit in 6 processes/', $results);
        $this->assertResults($results);
    }

    public function testWithBootstrapAndProcessesShortOption()
    {
        $results = $this->paratest(array('bootstrap' => BOOTSTRAP, 'p' => 6));
        $this->assertRegExp('/Running phpunit in 6 processes/', $results);
        $this->assertResults($results);
    }

    public function testWithBootstrapAndManuallySpecifiedPHPUnit()
    {
        $results = $this->paratest(array('bootstrap' => BOOTSTRAP, 'phpunit' => PHPUNIT));
        $this->assertResults($results);
    }

    public function testDefaultSettingsWithoutBootstrap()
    {
        chdir(PARATEST_ROOT);
        $result = $this->paratest();
        $this->assertResults($result);
    }

    public function testDefaultSettingsWithSpecifiedPath()
    {
        chdir(PARATEST_ROOT);
        $this->path = '';
        $result = $this->paratest(array('path' => 'test/fixtures/tests'));
        $this->assertResults($result);
    }

    public function testLoggingXmlOfDirectory()
    {
        chdir(PARATEST_ROOT);
        $output = FIXTURES . DS . 'logs' . DS . 'functional-directory.xml';
        $result = $this->paratest(array(
            'log-junit' => $output
        ));
        $this->assertResults($result);
        $this->assertTrue(file_exists($output));
        if(file_exists($output)) unlink($output);
    }

    public function testTestTokenEnvVarIsPassed()
    {
        chdir(PARATEST_ROOT);
        $this->path = '';
        $result = $this->paratest(array('path' => 'test/fixtures/tests/TestTokenTest.php'));
        $this->assertContains("OK (1 test, 1 assertion)", $result);
    }

    public function testLoggingXmlOfSingleFile()
    {
        chdir(PARATEST_ROOT);
        $output = FIXTURES . DS . 'logs' . DS . 'functional-file.xml';
        $this->path = FIXTURES . DS . 'tests' . DS . 'GroupsTest.php';
        $result = $this->paratest(array(
            'log-junit' => $output,
            'bootstrap' => BOOTSTRAP
        ));
        $this->assertRegExp("/OK \(5 tests, 5 assertions\)/", $result);
        $this->assertTrue(file_exists($output));
        if(file_exists($output)) unlink($output);
    }

    public function testSuccessfulRunHasExitCode0()
    {
        $this->path = FIXTURES . DS . 'tests' . DS . 'GroupsTest.php';
        $proc = $this->paratestProc(array(
            'bootstrap' => BOOTSTRAP
        ));
        $this->assertEquals(0, $this->getExitCode());
    }

    public function testFailedRunHasExitCode1()
    {
        $this->path = FIXTURES . DS . 'tests' . DS . 'TestOfUnits.php';
        $proc = $this->paratestProc(array(
            'bootstrap' => BOOTSTRAP
        ));
        $this->assertEquals(1, $this->getExitCode());
    }

    public function testRunWithErrorsHasExitCode2()
    {
        $this->path = FIXTURES . DS . 'tests' . DS . 'UnitTestWithErrorTest.php';
        $proc = $this->paratestProc(array(
            'bootstrap' => BOOTSTRAP
        ));
        $this->assertEquals(2, $this->getExitCode());
    }

    public function testRunWithFatalParseErrorsHasExitCode255()
    {
        $this->path = FIXTURES . DS . 'fatal-tests' . DS . 'UnitTestWithFatalParseErrorTest.php';
        $proc = $this->paratestProc(array(
            'bootstrap' => BOOTSTRAP
        ));
        $this->assertEquals(255, $this->getExitCode());
    }

    public function testRunWithFatalRuntimeErrorsHasExitCode1()
    {
        $this->path = FIXTURES . DS . 'fatal-tests' . DS . 'UnitTestWithFatalFunctionErrorTest.php';
        $proc = $this->paratestProc(array(
            'bootstrap' => BOOTSTRAP
        ));
        $this->assertEquals(1, $this->getExitCode());
    }

    public function testRunWithFatalRuntimeErrorOutputsError()
    {
        $this->path = FIXTURES . DS . 'fatal-tests' . DS . 'UnitTestWithFatalFunctionErrorTest.php';
        $pipes = array();
        $this->paratest(array(
            'bootstrap' => BOOTSTRAP
        ));
        $stderr = $this->getErrorOutput();
        $this->assertContains('Call to undefined function inexistent', $stderr);
    }

    public function testRunWithFatalRuntimeErrorWithTheWrapperRunnerOutputsError()
    {
        $this->path = FIXTURES . DS . 'fatal-tests' . DS . 'UnitTestWithFatalFunctionErrorTest.php';
        $pipes = array();
        $this->paratest(array(
            'bootstrap' => BOOTSTRAP,
            'runner' => 'WrapperRunner'
        ));
        $stderr = $this->getErrorOutput();
        $this->assertContains('Call to undefined function inexistent', $stderr);
    }

    public function testStopOnFailurePreventsStartingFurtherTestsAfterFailure()
    {
        $this->path = FIXTURES . DS . 'tests' . DS . 'StopOnFailureTest.php';
        $pipes = array();
        $results = $this->paratest(array(
            'bootstrap' => BOOTSTRAP,
            'stop-on-failure' => '',
            'f' => '',
            'p' => '2'
        ), $pipes);
        $this->assertContains('Tests: 2,', $results);     // The suite actually has 4 tests
        $this->assertContains('Failures: 1,', $results);  // The suite actually has 2 failing tests
    }

    /**
     * @todo something funny with this
     */
    /*public function testRunWithoutPathArgumentDisplaysUsage()
    {
        $this->path = '';
        $result = $this->paratest();
        $usage = file_get_contents(FIXTURES . DS . 'output' . DS . 'usage.txt');
        $this->assertEquals($usage, $result);
    }*/

    public function testFullyConfiguredRun()
    {
        $output = FIXTURES . DS . 'logs' . DS . 'functional.xml';
        $results = $this->paratest(array(
            'bootstrap' => BOOTSTRAP,
            'phpunit' => PHPUNIT,
            'f' => '',
            'p' => '6',
            'log-junit' => $output
        ));
        $this->assertRegExp('/Running phpunit in 6 processes/', $results);
        $this->assertRegExp('/Functional mode is on/i', $results);
        $this->assertResults($results);
        $this->assertTrue(file_exists($output));
        //the highest exit code presented should be what is returned
        $this->assertEquals(2, $this->getExitCode());
        if(file_exists($output)) unlink($output);
    }

    public function testUsingDefaultLoadedConfiguration()
    {
        chdir(FIXTURES);
        $results = $this->paratest(array('f' => ''));
        $this->assertResults($results);
    }

    public function testEachTestRunsExactlyOnceOnChainDependencyOnFunctionalMode()
    {
        $this->path = FIXTURES . DS . "tests" . DS . "DependsOnChain.php";
        $results = $this->paratest(array('bootstrap' => BOOTSTRAP, 'f' => ''));
        $this->assertOkResults($results, 5, 5);
    }

    public function testEachTestRunsExactlyOnceOnSameDependencyOnFunctionalMode()
    {
        $this->path = FIXTURES . DS . "tests" . DS . "DependsOnSame.php";
        $results = $this->paratest(array('bootstrap' => BOOTSTRAP, 'f' => ''));
        $this->assertOkResults($results, 3, 3);
    }

    public function testFunctionalModeEachTestCalledOnce()
    {
        $this->path = FIXTURES . DS . "tests" . DS . "FunctionalModeEachTestCalledOnce.php";
        $results = $this->paratest(array('bootstrap' => BOOTSTRAP, 'f' => ''));
        $this->assertOkResults($results, 2, 2);
    }

    protected function assertResults($results)
    {
        $this->assertRegExp("/FAILURES!
Tests: 39, Assertions: 42, Failures: 6, Errors: 1./", $results);
    }

    protected function assertOkResults($results, $tests, $assertions)
    {
        $regex = sprintf("/OK \(%d tests, %d assertions\)/", $tests, $assertions);
        $this->assertRegExp($regex, $results);
    }

    protected function paratest($options = array(), $switches = array())
    {
        $cmd = $this->getCmd($options, $switches);
        return $this->getTestOutput($cmd);
    }

    protected function paratestProc($options = array(), &$pipes = array())
    {
        $cmd = $this->getCmd($options);
        $proc = $this->getFinishedProc($cmd, $pipes);

        return $proc;
    }

    protected function getCmd($options = array(), $switches = array())
    {
        $cmd = PARA_BINARY;
        foreach($options as $switch => $value) {
            $cmd .= ' ' . $this->getOption($switch, $value);
        }
        foreach ($switches as $name) {
            $cmd .= " --$name";
        }
        $cmd .= ' ' . $this->path;
        return $cmd;
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
