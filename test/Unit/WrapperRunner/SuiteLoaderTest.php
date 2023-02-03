<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\WrapperRunner\PHPUnit\ExecutableTest;
use ParaTest\WrapperRunner\PHPUnit\FullSuite;
use ParaTest\WrapperRunner\PHPUnit\Suite;
use ParaTest\WrapperRunner\SuiteLoader;
use ParaTest\Tests\TestBase;
use Symfony\Component\Console\Output\BufferedOutput;
use function array_shift;

/**
 * @internal
 *
 * @covers \ParaTest\WrapperRunner\SuiteLoader
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
