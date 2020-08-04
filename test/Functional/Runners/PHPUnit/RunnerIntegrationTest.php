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
use function ob_end_clean;
use function ob_start;
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
            'path' => FIXTURES . DS . 'failing-tests',
            'phpunit' => PHPUNIT,
            'coverage-php' => sys_get_temp_dir() . DS . 'testcoverage.php',
            'bootstrap' => BOOTSTRAP,
            'whitelist' => FIXTURES . DS . 'failing-tests',
        ];
        $this->options     = new Options($this->bareOptions);
        $this->output      = new BufferedOutput();
        $this->runner      = new Runner($this->options, $this->output);
    }

    protected function tearDown(): void
    {
        $testcoverageFile = sys_get_temp_dir() . DS . 'testcoverage.php';
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

    public function testRunningTestsShouldLeaveNoTempFiles(): void
    {
        $countBefore         = count($this->globTempDir('PT_*'));
        $countCoverageBefore = count($this->globTempDir('CV_*'));

        ob_start();
        $this->runner->run();
        ob_end_clean();

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
        $outputPath                     = FIXTURES . DS . 'logs' . DS . 'test-output.xml';
        $this->bareOptions['log-junit'] = $outputPath;
        $runner                         = new Runner(new Options($this->bareOptions), $this->output);

        ob_start();
        $runner->run();
        ob_end_clean();

        static::assertFileExists($outputPath);
        $this->assertJunitXmlIsCorrect($outputPath);
        if (! file_exists($outputPath)) {
            return;
        }

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
