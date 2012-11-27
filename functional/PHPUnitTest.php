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

    public function testFunctionalWithBootstrap()
    {
        $results = $this->paratest(array('bootstrap' => BOOTSTRAP, 'functional' => ''));
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

    public function testFullyConfiguredRunAssumingCurrentDirectory()
    {
        $this->path = '';
        $results = $this->paratest(array(
            'bootstrap' => BOOTSTRAP,
            'phpunit' => PHPUNIT,
            'f' => '',
            'p' => '6'
        ));
        $this->assertRegExp('/Running phpunit in 6 processes/', $results);
        $this->assertRegExp('/Functional mode is on/i', $results);
        $this->assertResults($results);
    }

    protected function assertResults($results)
    {
        $this->assertRegExp("/FAILURES!
Tests: 31, Assertions: 30, Failures: 4, Errors: 1./", $results);
    }

    protected function paratest($options = array())
    {
       $cmd = PARA_BINARY;
       foreach($options as $switch => $value)
           $cmd .= ' ' . $this->getOption($switch, $value);
       $cmd .= ' ' . $this->path;
       return $this->getTestOutput($cmd);
    }
}