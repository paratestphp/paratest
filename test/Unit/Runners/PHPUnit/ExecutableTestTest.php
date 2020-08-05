<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Tests\TestBase;

use function defined;
use function preg_quote;
use function str_replace;
use function unlink;

final class ExecutableTestTest extends TestBase
{
    /** @var ExecutableTestChild */
    protected $executableTestChild;

    public function setUp(): void
    {
        $this->executableTestChild = new ExecutableTestChild('pathToFile');
    }

    public function testConstructor(): void
    {
        static::assertEquals('pathToFile', $this->getObjectValue($this->executableTestChild, 'path'));
    }

    public function testCommandRedirectsCoverage(): void
    {
        $options = ['a' => 'b', 'coverage-php' => 'target.php'];
        $binary  = '/usr/bin/phpunit';

        $command          = $this->executableTestChild->command($binary, $options);
        $coverageFileName = str_replace('/', '\/', $this->executableTestChild->getCoverageFileName());

        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            static::assertMatchesRegularExpression(
                '#^"/usr/bin/phpunit" --a b --coverage-php ' . preg_quote($coverageFileName, '#') . ' .*#',
                $command
            );
        } else {
            static::assertMatchesRegularExpression(
                "#^'/usr/bin/phpunit' '--a' 'b' '--coverage-php' '" . $coverageFileName . "' '.*'#",
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
