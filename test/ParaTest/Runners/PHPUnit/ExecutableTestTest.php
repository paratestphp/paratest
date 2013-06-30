<?php
namespace ParaTest\Runners\PHPUnit;

class ExecutableTestTest extends \TestBase
{
    /**
     *
     * @var ExecutableTestChild
     */
    protected $executableTestChild;

    public function setUp()
    {
        $this->executableTestChild = new ExecutableTestChild('pathToFile', 'ClassNameTest');
        parent::setUp();
    }

    public function testConstructor()
    {
          $this->assertEquals('pathToFile', $this->getObjectValue($this->executableTestChild, 'path'));
    }

    public function testGetCommandStringIncludesOptions()
    {
        $options = array('bootstrap' => 'test/bootstrap.php');
        $binary = '/usr/bin/phpunit';

        $command = $this->call($this->executableTestChild, 'getCommandString', $binary, $options);
        $this->assertEquals('PARATEST=1 /usr/bin/phpunit --bootstrap test/bootstrap.php ClassNameTest pathToFile', $command);
    }

    public function testGetCommandStringIncludesEnvironmentVariables()
    {
        $options = array('bootstrap' => 'test/bootstrap.php');
        $binary = '/usr/bin/phpunit';
        $environmentVariables = array('TEST_TOKEN' => 3, 'APPLICATION_ENVIRONMENT_VAR' => 'abc');
        $command = $this->call($this->executableTestChild, 'getCommandString', $binary, $options, $environmentVariables);

        $this->assertEquals('PARATEST=1 TEST_TOKEN=3 APPLICATION_ENVIRONMENT_VAR=abc /usr/bin/phpunit --bootstrap test/bootstrap.php ClassNameTest pathToFile', $command);
    }

    public function testGetCommandStringIncludesTheClassName()
    {
        $options = array();
        $binary = '/usr/bin/phpunit';

        $command = $this->call($this->executableTestChild, 'getCommandString', $binary, $options);
        $this->assertEquals('PARATEST=1 /usr/bin/phpunit ClassNameTest pathToFile', $command);
    }

    public function testHandleEnvironmentVariablesAssignsToken()
    {
        $environmentVariables = array('TEST_TOKEN' => 3, 'APPLICATION_ENVIRONMENT_VAR' => 'abc');
        $this->call($this->executableTestChild, 'handleEnvironmentVariables', $environmentVariables);
        $this->assertEquals(3, $this->getObjectValue($this->executableTestChild, 'token'));
    }

    public function testGetTokenReturnsValidToken()
    {
        $this->setObjectValue($this->executableTestChild, 'token', 3);
        $this->assertEquals(3, $this->executableTestChild->getToken());
    }

    public function testGetTempFileShouldCreateTempFile()
    {
        $file = $this->executableTestChild->getTempFile();
        $this->assertTrue(file_exists($file));
        unlink($file);
    }

    public function testGetTempFileShouldReturnSameFileIfAlreadyCalled()
    {
        $file = $this->executableTestChild->getTempFile();
        $fileAgain = $this->executableTestChild->getTempFile();
        $this->assertEquals($file, $fileAgain);
        unlink($file);
    }
}

class ExecutableTestChild extends ExecutableTest
{

}
