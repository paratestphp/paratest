<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\Runner;
use ParaTest\Tests\TestBase;
use Symfony\Component\Console\Output\BufferedOutput;

use function count;
use function file_exists;
use function glob;
use function simplexml_load_file;
use function sys_get_temp_dir;
use function unlink;

final class RunnerIntegrationTest extends TestBase
{
    /** @var Runner $runner */
    private $runner;
    /** @var BufferedOutput */
    private $output;
    /** @var array<string, string> */
    private $bareOptions;
    /** @var Options */
    private $options;

    protected function setUp(): void
    {
        static::skipIfCodeCoverageNotEnabled();

        $this->bareOptions = [
            '--path' => FIXTURES . DS . 'failing-tests',
            '--phpunit' => PHPUNIT,
            '--coverage-clover' => sys_get_temp_dir() . DS . 'coverage.clover',
            '--coverage-crap4j' => sys_get_temp_dir() . DS . 'coverage.crap4j',
            '--coverage-php' => sys_get_temp_dir() . DS . 'coverage.php',
            '--coverage-xml' => sys_get_temp_dir() . DS . 'coverage.xml',
            '--bootstrap' => BOOTSTRAP,
            '--whitelist' => FIXTURES . DS . 'failing-tests',
        ];
        $this->options     = $this->createOptionsFromArgv($this->bareOptions);
        $this->output      = new BufferedOutput();
        $this->runner      = new Runner($this->options, $this->output);
    }

    protected function tearDown(): void
    {
        $testcoverageFile = sys_get_temp_dir() . DS . 'coverage*';
        if (file_exists($testcoverageFile)) {
            unlink($testcoverageFile);
        }

        parent::tearDown();
    }

    /**
     * @return string[]
     */
    private function globTempDir(string $pattern): array
    {
        return glob(sys_get_temp_dir() . DS . $pattern);
    }

    public function testGeneratesCoverageTypes(): void
    {
        static::assertFileDoesNotExist($this->bareOptions['--coverage-clover']);
        static::assertFileDoesNotExist($this->bareOptions['--coverage-crap4j']);
        static::assertFileDoesNotExist($this->bareOptions['--coverage-php']);
        static::assertFileDoesNotExist($this->bareOptions['--coverage-xml']);

        $this->runner->run();

        static::assertFileExists($this->bareOptions['--coverage-clover']);
        static::assertFileExists($this->bareOptions['--coverage-crap4j']);
        static::assertFileExists($this->bareOptions['--coverage-php']);
        static::assertFileExists($this->bareOptions['--coverage-xml']);
    }

    public function testRunningTestsShouldLeaveNoTempFiles(): void
    {
        $countBefore         = count($this->globTempDir('PT_*'));
        $countCoverageBefore = count($this->globTempDir('CV_*'));

        $this->runner->run();

        $countAfter         = count($this->globTempDir('PT_*'));
        $countCoverageAfter = count($this->globTempDir('CV_*'));

        static::assertEquals(
            $countAfter,
            $countBefore,
            "Test Runner failed to clean up the 'PT_*' file in " . sys_get_temp_dir()
        );
        static::assertEquals(
            $countCoverageAfter,
            $countCoverageBefore,
            "Test Runner failed to clean up the 'CV_*' file in " . sys_get_temp_dir()
        );
    }

    public function testLogJUnitCreatesXmlFile(): void
    {
        $outputPath = FIXTURES . DS . 'logs' . DS . 'test-output.xml';

        $this->bareOptions['--log-junit'] = $outputPath;

        $runner = new Runner($this->createOptionsFromArgv($this->bareOptions), $this->output);

        $runner->run();

        static::assertFileExists($outputPath);
        $this->assertJunitXmlIsCorrect($outputPath);
        unlink($outputPath);
    }

    public function assertJunitXmlIsCorrect(string $path): void
    {
        $doc      = simplexml_load_file($path);
        $suites   = $doc->xpath('//testsuite');
        $cases    = $doc->xpath('//testcase');
        $failures = $doc->xpath('//failure');
        $errors   = $doc->xpath('//error');

        // these numbers represent the tests in fixtures/failing-tests
        // so will need to be updated when tests are added or removed
        static::assertCount(6, $suites);
        static::assertCount(16, $cases);
        static::assertCount(6, $failures);
        static::assertCount(1, $errors);
    }
}
