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

    public function testWithJustConfiguration()
    {
        $results = $this->paratest(array('configuration' => PHPUNIT_CONFIGURATION));
        $this->assertResults($results);
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
        $proc = $this->paratestProc(array(
            'bootstrap' => BOOTSTRAP
        ), $pipes);
        $this->assertContains('Call to undefined function inexistent', stream_get_contents($pipes[2]));
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

    protected function assertResults($results)
    {
        $this->assertRegExp("/FAILURES!
Tests: 31, Assertions: 30, Failures: 4, Errors: 1./", $results);
    }

    protected function paratest($options = array())
    {
        $cmd = $this->getCmd($options);
        return $this->getTestOutput($cmd);
    }

    protected function paratestProc($options = array(), &$pipes = array())
    {
        $cmd = $this->getCmd($options);
        $proc = $this->getFinishedProc($cmd, $pipes);
        return $proc;
    }

    protected function getCmd($options = array())
    {
        $cmd = PARA_BINARY;
        foreach($options as $switch => $value)
            $cmd .= ' ' . $this->getOption($switch, $value);
        $cmd .= ' ' . $this->path;
        return $cmd;
    }
}