<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Tests\TestBase;

use function defined;
use function unlink;

/**
 * @coversNothing
 */
final class ExecutableTestTest extends TestBase
{
    /** @var ExecutableTestChild */
    protected $executableTestChild;

    public function setUpTest(): void
    {
        $this->executableTestChild = new ExecutableTestChild('pathToFile', true, TMP_DIR);
    }

    public function testConstructor(): void
    {
        static::assertEquals('pathToFile', $this->getObjectValue($this->executableTestChild, 'path'));
    }

    public function testCommandRedirectsCoverage(): void
    {
        $options = ['a' => 'b'];
        $binary  = '/usr/bin/phpunit';

        $command = $this->executableTestChild->command($binary, $options);

        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            static::assertMatchesRegularExpression(
                '#^"/usr/bin/phpunit" --a b .+#',
                $command
            );
        } else {
            static::assertMatchesRegularExpression(
                "#^'/usr/bin/phpunit' '--a' 'b' .+#",
                $command
            );
        }
    }

    public function testGetTempFileShouldCreateTempFile(): void
    {
        $file = $this->executableTestChild->getTempFile();
        static::assertFileExists($file);
        unlink($file);
    }

    public function testGetTempFileShouldReturnSameFileIfAlreadyCalled(): void
    {
        $file      = $this->executableTestChild->getTempFile();
        $fileAgain = $this->executableTestChild->getTempFile();
        static::assertEquals($file, $fileAgain);
        unlink($file);
    }
}
