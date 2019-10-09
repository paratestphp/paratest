<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use Symfony\Component\Process\PhpExecutableFinder;

class ExecutableTestTest extends \ParaTest\Tests\TestBase
{
    /**
     * @var ExecutableTestChild
     */
    protected $executableTestChild;

    public function setUp(): void
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
        $options = ['bootstrap' => 'test/bootstrap.php'];
        $binary = '/usr/bin/phpunit';

        $command = $this->call($this->executableTestChild, 'getCommandString', $binary, $options);
        $this->assertEquals(
            "'/usr/bin/phpunit' '--bootstrap' 'test/bootstrap.php' 'ClassNameTest' 'pathToFile'",
            $command
        );
    }

    public function testGetCommandStringIncludesPassthruOptions()
    {
        $options = ['bootstrap' => 'test/bootstrap.php'];
        $binary = '/usr/bin/phpunit';
        $passthru = "'--prepend' 'xdebug-filter.php'";
        $passthruPhp = "'-d' 'zend_extension=xdebug.so'";

        $command = $this->call(
            $this->executableTestChild,
            'getFullCommandlineString',
            $binary,
            $options,
            $passthru,
            $passthruPhp
        );
        // Note:
        // '--log-junit' '/tmp/PT_LKnfzA'
        // is appended by default where PT_LKnfzA is randomly generated - so we remove it from the resulting command
        $command = preg_replace("# '--log-junit' '[^']+?'#", '', $command);
        // Note:
        // The pass to the php executable depends on the system,
        // so we need to keep it flexible in the test
        $finder = new PhpExecutableFinder();
        $phpExecutable = $finder->find();
        $this->assertEquals(
            "$phpExecutable '-d' 'zend_extension=xdebug.so' '/usr/bin/phpunit' '--prepend' 'xdebug-filter.php' " .
                "'--bootstrap' 'test/bootstrap.php' 'ClassNameTest' 'pathToFile'",
            $command
        );
    }

    public function testCommandRedirectsCoverage()
    {
        $options = ['a' => 'b', 'coverage-php' => 'target_html', 'coverage-php' => 'target.php'];
        $binary = '/usr/bin/phpunit';

        $command = $this->executableTestChild->command($binary, $options);
        $coverageFileName = str_replace('/', '\/', $this->executableTestChild->getCoverageFileName());
        $this->assertRegExp("/^'\/usr\/bin\/phpunit' '--a' 'b' '--coverage-php' '$coverageFileName' '.*'/", $command);
    }

    public function testGetCommandStringIncludesTheClassName()
    {
        $options = [];
        $binary = '/usr/bin/phpunit';

        $command = $this->call($this->executableTestChild, 'getCommandString', $binary, $options);
        $this->assertEquals("'/usr/bin/phpunit' 'ClassNameTest' 'pathToFile'", $command);
    }

    public function testHandleEnvironmentVariablesAssignsToken()
    {
        $environmentVariables = ['TEST_TOKEN' => 3, 'APPLICATION_ENVIRONMENT_VAR' => 'abc'];
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
        $this->assertFileExists($file);
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
