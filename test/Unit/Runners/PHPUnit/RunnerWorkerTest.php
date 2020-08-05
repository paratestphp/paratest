<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Worker\RunnerWorker;
use ParaTest\Tests\TestBase;
use Symfony\Component\Process\PhpExecutableFinder;

use function defined;
use function preg_replace;

final class RunnerWorkerTest extends TestBase
{
    /** @var RunnerWorker */
    private $runnerWorker;

    public function setUp(): void
    {
        $this->runnerWorker = new RunnerWorker(new ExecutableTestChild('pathToFile'));
        parent::setUp();
    }

    public function testGetCommandStringIncludesPassthruOptions(): void
    {
        $options     = ['bootstrap' => 'test' . DS . 'bootstrap.php'];
        $binary      = '/usr/bin/phpunit';
        $passthru    = ['--prepend', 'xdebug-filter.php'];
        $passthruPhp = ['-d', 'zend_extension=xdebug.so'];

        $command = $this->call(
            $this->runnerWorker,
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
            static::assertEquals(
                "$phpExecutable -d zend_extension=xdebug.so \"/usr/bin/phpunit\" --prepend xdebug-filter.php " .
                    '--bootstrap test' . DS . 'bootstrap.php pathToFile',
                $command
            );
        } else {
            static::assertEquals(
                "'$phpExecutable' '-d' 'zend_extension=xdebug.so' '/usr/bin/phpunit' '--prepend' 'xdebug-filter.php' " .
                    "'--bootstrap' 'test" . DS . "bootstrap.php' 'pathToFile'",
                $command
            );
        }
    }
}
