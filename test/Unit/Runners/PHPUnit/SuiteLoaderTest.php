<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\FullSuite;
use ParaTest\Runners\PHPUnit\Suite;
use ParaTest\Runners\PHPUnit\SuiteLoader;
use ParaTest\Tests\TestBase;
use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

use function array_keys;
use function array_shift;
use function count;
use function glob;
use function preg_match;
use function strstr;
use function uniqid;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\SuiteLoader
 */
final class SuiteLoaderTest extends TestBase
{
    private BufferedOutput $output;

    protected function setUpTest(): void
    {
        $this->output = new BufferedOutput();
    }

    public function testLoadTestsuiteFileFromConfig(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-file.xml');

        $loader = $this->loadSuite();

        static::assertSame(5, $loader->testCount);
        static::assertCount(1, $loader->files);
    }

    public function testLoadFileGetsPathOfFile(): void
    {
        $path  = $this->fixture('failing_tests' . DS . 'UnitTestWithClassAnnotationTest.php');
        $this->bareOptions['path'] = $path;
        $files = $this->loadSuite()->files;

        static::assertStringContainsString(array_shift($files), $path);
    }

    public function testCacheIsWarmedWhenSpecified(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-coverage-cache.xml');
        $this->loadSuite();

        static::assertStringContainsString('Warming cache', $this->output->fetch());
    }

    private function loadSuite(?string $cwd = null): SuiteLoader
    {
        $options = $this->createOptionsFromArgv($this->bareOptions, $cwd);

        return new SuiteLoader($options, $this->output);
    }
}
