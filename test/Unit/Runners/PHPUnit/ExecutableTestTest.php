<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Tests\TestBase;

use function uniqid;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\ExecutableTest
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
        static::assertEquals('pathToFile', $this->executableTestChild->getPath());
    }

    public function testCommandRedirectsCoverage(): void
    {
        $binary   = uniqid('phpunit');
        $options  = ['a' => 'b', 'no-coverage' => null];
        $passthru = ['--no-extensions'];

        $commandArguments = $this->executableTestChild->commandArguments($binary, $options, $passthru);

        $expected = [
            $binary,
            '--no-extensions',
            '--a',
            'b',
            '--no-coverage',
            '--log-junit',
            $this->executableTestChild->getTempFile(),
            '--coverage-php',
            $this->executableTestChild->getCoverageFileName(),
            'pathToFile',
        ];

        static::assertSame($expected, $commandArguments);
    }

    public function testGetTempFileShouldCreateTempFile(): void
    {
        $logFile = $this->executableTestChild->getTempFile();
        static::assertFileExists($logFile);
        $this->executableTestChild->deleteFile();
        static::assertFileDoesNotExist($logFile);

        $ccFile = $this->executableTestChild->getCoverageFileName();
        static::assertFileExists($ccFile);
        $this->executableTestChild->deleteFile();
        static::assertFileDoesNotExist($ccFile);
    }

    public function testGetTempFileShouldReturnSameFileIfAlreadyCalled(): void
    {
        $file      = $this->executableTestChild->getTempFile();
        $fileAgain = $this->executableTestChild->getTempFile();
        static::assertEquals($file, $fileAgain);
    }

    public function testStoreLastCommand(): void
    {
        static::assertEmpty($this->executableTestChild->getLastCommand());

        $this->executableTestChild->setLastCommand($lastCommand = uniqid());

        static::assertSame($lastCommand, $this->executableTestChild->getLastCommand());
    }
}
