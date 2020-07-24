<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Tests\TestBase;
use Symfony\Component\Process\PhpExecutableFinder;

use function defined;
use function preg_quote;
use function preg_replace;
use function str_replace;
use function unlink;

class ExecutableTestTest extends TestBase
{
    /** @var ExecutableTestChild */
    protected $executableTestChild;

    public function setUp(): void
    {
        $this->executableTestChild = new ExecutableTestChild('pathToFile');
        parent::setUp();
    }

    public function testConstructor(): void
    {
        $this->assertEquals('pathToFile', $this->getObjectValue($this->executableTestChild, 'path'));
    }

    public function testGetCommandStringIncludesPassthruOptions(): void
    {
        $options     = ['bootstrap' => 'test' . DS . 'bootstrap.php'];
        $binary      = '/usr/bin/phpunit';
        $passthru    = ['--prepend', 'xdebug-filter.php'];
        $passthruPhp = ['-d', 'zend_extension=xdebug.so'];

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
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $command = preg_replace('# --log-junit [^ ]+#', '', $command);
        } else {
            $command = preg_replace("# '--log-junit' '[^']+?'#", '', $command);
        }

        // Note:
        // The pass to the php executable depends on the system,
        // so we need to keep it flexible in the test
        $finder        = new PhpExecutableFinder();
        $phpExecutable = $finder->find();

        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->assertEquals(
                "$phpExecutable -d zend_extension=xdebug.so \"/usr/bin/phpunit\" --prepend xdebug-filter.php " .
                    '--bootstrap test' . DS . 'bootstrap.php pathToFile',
                $command
            );
        } else {
            $this->assertEquals(
                "'$phpExecutable' '-d' 'zend_extension=xdebug.so' '/usr/bin/phpunit' '--prepend' 'xdebug-filter.php' " .
                    "'--bootstrap' 'test" . DS . "bootstrap.php' 'pathToFile'",
                $command
            );
        }
    }

    public function testCommandRedirectsCoverage(): void
    {
        $options = ['a' => 'b', 'coverage-php' => 'target.php'];
        $binary  = '/usr/bin/phpunit';

        $command          = $this->executableTestChild->command($binary, $options);
        $coverageFileName = str_replace('/', '\/', $this->executableTestChild->getCoverageFileName());

        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->assertMatchesRegularExpression(
                '#^"/usr/bin/phpunit" --a b --coverage-php ' . preg_quote($coverageFileName, '#') . ' .*#',
                $command
            );
        } else {
            $this->assertMatchesRegularExpression(
                "#^'/usr/bin/phpunit' '--a' 'b' '--coverage-php' '" . $coverageFileName . "' '.*'#",
                $command
            );
        }
    }

    public function testHandleEnvironmentVariablesAssignsToken(): void
    {
        $environmentVariables = ['TEST_TOKEN' => 3, 'APPLICATION_ENVIRONMENT_VAR' => 'abc'];
        $this->call($this->executableTestChild, 'handleEnvironmentVariables', $environmentVariables);
        $this->assertEquals(3, $this->getObjectValue($this->executableTestChild, 'token'));
    }

    public function testGetTokenReturnsValidToken(): void
    {
        $this->setObjectValue($this->executableTestChild, 'token', 3);
        $this->assertEquals(3, $this->executableTestChild->getToken());
    }

    public function testGetTempFileShouldCreateTempFile(): void
    {
        $file = $this->executableTestChild->getTempFile();
        $this->assertFileExists($file);
        unlink($file);
    }

    public function testGetTempFileShouldReturnSameFileIfAlreadyCalled(): void
    {
        $file      = $this->executableTestChild->getTempFile();
        $fileAgain = $this->executableTestChild->getTempFile();
        $this->assertEquals($file, $fileAgain);
        unlink($file);
    }
}
