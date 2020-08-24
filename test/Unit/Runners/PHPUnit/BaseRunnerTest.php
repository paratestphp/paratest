<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Tests\TestBase;

use function assert;
use function count;
use function glob;
use function simplexml_load_file;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\BaseRunner
 */
final class BaseRunnerTest extends TestBase
{
    protected function setUpTest(): void
    {
        static::skipIfCodeCoverageNotEnabled();

        $this->bareOptions = [
            '--path' => $this->fixture('failing_tests'),
            '--coverage-clover' => TMP_DIR . DS . 'coverage.clover',
            '--coverage-crap4j' => TMP_DIR . DS . 'coverage.crap4j',
            '--coverage-html' => TMP_DIR . DS . 'coverage.html',
            '--coverage-php' => TMP_DIR . DS . 'coverage.php',
            '--coverage-text' => true,
            '--coverage-xml' => TMP_DIR . DS . 'coverage.xml',
            '--bootstrap' => BOOTSTRAP,
            '--whitelist' => $this->fixture('failing_tests'),
        ];
    }

    /**
     * @return string[]
     */
    private function globTempDir(string $pattern): array
    {
        $glob = glob(TMP_DIR . DS . $pattern);
        assert($glob !== false);

        return $glob;
    }

    public function testGeneratesCoverageTypes(): void
    {
        static::assertFileDoesNotExist((string) $this->bareOptions['--coverage-clover']);
        static::assertFileDoesNotExist((string) $this->bareOptions['--coverage-crap4j']);
        static::assertFileDoesNotExist((string) $this->bareOptions['--coverage-html']);
        static::assertFileDoesNotExist((string) $this->bareOptions['--coverage-php']);
        static::assertFileDoesNotExist((string) $this->bareOptions['--coverage-xml']);

        $this->bareOptions['--configuration'] = $this->fixture('phpunit-fully-configured.xml');
        $runnerResult                         = $this->runRunner();

        static::assertFileExists((string) $this->bareOptions['--coverage-clover']);
        static::assertFileExists((string) $this->bareOptions['--coverage-crap4j']);
        static::assertFileExists((string) $this->bareOptions['--coverage-html']);
        static::assertFileExists((string) $this->bareOptions['--coverage-php']);
        static::assertFileExists((string) $this->bareOptions['--coverage-xml']);

        static::assertStringContainsString('Code Coverage Report:', $runnerResult->getOutput());
        static::assertStringContainsString('Generating code coverage', $runnerResult->getOutput());
    }

    public function testRunningTestsShouldLeaveNoTempFiles(): void
    {
        // Needed for one line coverage on early exit CS Fix :\
        unset($this->bareOptions['--coverage-php']);

        $countBefore         = count($this->globTempDir('PT_*'));
        $countCoverageBefore = count($this->globTempDir('CV_*'));

        $this->runRunner();

        $countAfter         = count($this->globTempDir('PT_*'));
        $countCoverageAfter = count($this->globTempDir('CV_*'));

        static::assertSame(
            $countAfter,
            $countBefore,
            "Test Runner failed to clean up the 'PT_*' file in " . TMP_DIR
        );
        static::assertSame(
            $countCoverageAfter,
            $countCoverageBefore,
            "Test Runner failed to clean up the 'CV_*' file in " . TMP_DIR
        );
    }

    public function testLogJUnitCreatesXmlFile(): void
    {
        $outputPath = TMP_DIR . DS . 'test-output.xml';

        $this->bareOptions['--log-junit'] = $outputPath;

        $this->runRunner();

        static::assertFileExists($outputPath);
        $this->assertJunitXmlIsCorrect($outputPath);
    }

    public function assertJunitXmlIsCorrect(string $path): void
    {
        $doc = simplexml_load_file($path);
        assert($doc !== false);
        $suites   = $doc->xpath('//testsuite');
        $cases    = $doc->xpath('//testcase');
        $failures = $doc->xpath('//failure');
        $warnings = $doc->xpath('//warning');
        $skipped  = $doc->xpath('//skipped');
        $errors   = $doc->xpath('//error');

        // these numbers represent the tests in fixtures/failing_tests
        // so will need to be updated when tests are added or removed
        static::assertNotFalse($suites);
        static::assertCount(5, $suites);
        static::assertNotFalse($cases);
        static::assertCount(17, $cases);
        static::assertNotFalse($failures);
        static::assertCount(5, $failures);
        static::assertNotFalse($warnings);
        static::assertCount(1, $warnings);
        static::assertNotFalse($skipped);
        static::assertCount(2, $skipped);
        static::assertNotFalse($errors);
        static::assertCount(2, $errors);
    }
}
