<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\WrapperRunner;

use ParaTest\Options;
use ParaTest\Tests\TestBase;
use ParaTest\WrapperRunner\ResultPrinter;
use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Configuration\Configuration;
use SebastianBergmann\Environment\Runtime;
use SplFileInfo;
use Symfony\Component\Console\Output\BufferedOutput;

use function file_get_contents;
use function file_put_contents;
use function phpversion;
use function sprintf;
use function uniqid;

use const DIRECTORY_SEPARATOR;
use const PHP_VERSION;

/**
 * @internal
 *
 * @covers \ParaTest\WrapperRunner\ResultPrinter
 */
final class ResultPrinterTest extends TestBase
{
    private ResultPrinter $printer;
    private BufferedOutput $output;
    private Options $options;

    protected function setUpTest(): void
    {
        $this->output  = new BufferedOutput();
        $this->options = $this->createOptionsFromArgv(['--verbose' => true], __DIR__);
        $this->printer = new ResultPrinter($this->output, $this->options);
    }

    public function testStartPrintsOptionInfo(): void
    {
        $contents = $this->getStartOutput();
        $expected = sprintf("Processes:     %s\n", PROCESSES_FOR_TESTS);

        self::assertStringStartsWith($expected, $contents);
    }

    public function testStartPrintsRuntimeInfosWithoutCcDriver(): void
    {
        if ((new Runtime())->hasPCOV()) {
            self::markTestSkipped('PCOV loaded');
        }

        $this->printer = new ResultPrinter($this->output, $this->createOptionsFromArgv(['--verbose' => true]));
        $contents      = $this->getStartOutput();

        self::assertStringContainsString(sprintf("Runtime:       PHP %s\n", PHP_VERSION), $contents);
    }

    public function testStartPrintsRuntimeInfosWithCcDriver(): void
    {
        if (! (new Runtime())->hasPCOV()) {
            self::markTestSkipped('PCOV not loaded');
        }

        $this->printer = new ResultPrinter($this->output, $this->createOptionsFromArgv([
            '--verbose' => true,
            '--coverage-text' => 'php://stdout',
        ]));
        $contents      = $this->getStartOutput();

        self::assertStringContainsString(sprintf("Runtime:       PHP %s with PCOV %s\n", PHP_VERSION, phpversion('pcov')), $contents);
    }

    public function testStartPrintsOptionInfoAndConfigurationDetailsIfConfigFilePresent(): void
    {
        $pathToConfig = $this->tmpDir . DIRECTORY_SEPARATOR . 'phpunit-myconfig.xml';

        file_put_contents($pathToConfig, '<phpunit />');
        $this->printer = new ResultPrinter($this->output, $this->createOptionsFromArgv([
            '--configuration' => $pathToConfig,
            '--verbose' => true,
        ]));
        $contents      = $this->getStartOutput();
        $expected      = sprintf("Configuration: %s\n\n", $pathToConfig);
        self::assertStringEndsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithRandom(): void
    {
        $pathToConfig = $this->tmpDir . DIRECTORY_SEPARATOR . 'phpunit-myconfig.xml';

        file_put_contents($pathToConfig, '<phpunit />');
        $random_seed   = 1234;
        $this->printer = new ResultPrinter($this->output, $this->createOptionsFromArgv([
            '--configuration' => $pathToConfig,
            '--order-by' => 'random',
            '--random-order-seed' => (string) $random_seed,
            '--verbose' => true,
        ]));
        $contents      = $this->getStartOutput();
        $expected      = sprintf("Random Seed:   %s\n\n", $random_seed);

        self::assertStringEndsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithSingularForOneProcess(): void
    {
        $this->printer = new ResultPrinter($this->output, $this->createOptionsFromArgv([
            '--processes' => '1',
            '--verbose' => true,
        ]));
        $contents      = $this->getStartOutput();

        self::assertStringStartsWith("Processes:     1\n", $contents);
    }

    public function testGetHeader(): void
    {
        $this->printer->printResults($this->getEmptyTestResult(), [], []);

        self::assertMatchesRegularExpression(
            "/\nTime: ([.:]?[0-9]{1,3})+ ?" .
            '(minute|minutes|second|seconds|ms|)?,' .
            " Memory:[\\s][0-9]+([.][0-9]{1,2})? ?M[Bb]\n\n/",
            $this->output->fetch(),
        );
    }

    public function testPrintFeedbackForMixed(): void
    {
        $this->printer->setTestCount(20);
        $feedbackFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'feedback1';
        file_put_contents($feedbackFile, 'EWWFFFRRSSSS....... 19 / 19 (100%)');
        $this->printer->printFeedback(new SplFileInfo($feedbackFile), []);
        $contents = $this->output->fetch();
        self::assertSame('EWWFFFRRSSSS.......', $contents);

        $feedbackFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'feedback2';
        file_put_contents($feedbackFile, 'E 1 / 1 (100%)');
        $this->printer->printFeedback(new SplFileInfo($feedbackFile), []);
        $contents = $this->output->fetch();
        self::assertSame("E 20 / 20 (100%)\n", $contents);
    }

    public function testColorsForFailing(): void
    {
        $this->options = $this->createOptionsFromArgv(['--colors' => Configuration::COLOR_ALWAYS]);
        $this->printer = new ResultPrinter($this->output, $this->options);
        $this->printer->setTestCount(20);
        $feedbackFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'feedback1';
        file_put_contents($feedbackFile, 'E');
        $this->printer->printFeedback(new SplFileInfo($feedbackFile), []);
        $contents = $this->output->fetch();
        self::assertStringContainsString('E', $contents);
        self::assertStringContainsString('31;1', $contents);
    }

    public function testTeamcityFeedbackOnFile(): void
    {
        $teamcitySource        = $this->tmpDir . DIRECTORY_SEPARATOR . 'source';
        $teamcitySourceContent = uniqid('##teamcity_');
        file_put_contents($teamcitySource, $teamcitySourceContent);
        $teamcityLog = $this->tmpDir . DIRECTORY_SEPARATOR . 'teamcity2.log';

        $this->options = $this->createOptionsFromArgv(['--log-teamcity' => $teamcityLog]);
        $this->printer = new ResultPrinter($this->output, $this->options);

        $this->printer->setTestCount(20);
        $feedbackFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'feedback1';
        file_put_contents($feedbackFile, 'E');

        $this->printer->printFeedback(new SplFileInfo($feedbackFile), [new SplFileInfo($teamcitySource)]);

        self::assertSame('E', $this->output->fetch());
        self::assertFileExists($teamcityLog);

        $logContent = file_get_contents($teamcityLog);

        self::assertNotFalse($logContent);
        self::assertSame($teamcitySourceContent, $logContent);
    }

    public function testTeamcityFeedbackOnStdout(): void
    {
        $teamcitySource        = $this->tmpDir . DIRECTORY_SEPARATOR . 'source';
        $teamcitySourceContent = uniqid('##teamcity_');
        file_put_contents($teamcitySource, $teamcitySourceContent);

        $this->options = $this->createOptionsFromArgv(['--teamcity' => true]);
        $this->printer = new ResultPrinter($this->output, $this->options);

        $this->printer->setTestCount(20);
        $feedbackFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'feedback1';
        file_put_contents($feedbackFile, 'E');

        $this->printer->printFeedback(new SplFileInfo($feedbackFile), [new SplFileInfo($teamcitySource)]);
        $this->printer->printResults($this->getEmptyTestResult(), [new SplFileInfo($teamcitySource)], []);

        self::assertSame($teamcitySourceContent, $this->output->fetch());
    }

    public function testTestdoxOutputWithProgress(): void
    {
        $testdoxSource        = $this->tmpDir . DIRECTORY_SEPARATOR . 'source';
        $testdoxSourceContent = uniqid('Success!');
        file_put_contents($testdoxSource, $testdoxSourceContent);

        $this->options = $this->createOptionsFromArgv(['--testdox' => true]);
        $this->printer = new ResultPrinter($this->output, $this->options);

        $this->printer->setTestCount(20);
        $feedbackFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'feedback1';
        file_put_contents($feedbackFile, 'EEE');

        $this->printer->printFeedback(new SplFileInfo($feedbackFile), []);
        $this->printer->printResults($this->getEmptyTestResult(), [], [new SplFileInfo($testdoxSource)]);

        self::assertSame('EEE' . $testdoxSourceContent, $this->output->fetch());
    }

    public function testTestdoxOutputWithoutProgress(): void
    {
        $testdoxSource        = $this->tmpDir . DIRECTORY_SEPARATOR . 'source';
        $testdoxSourceContent = uniqid('Success!');
        file_put_contents($testdoxSource, $testdoxSourceContent);

        $this->options = $this->createOptionsFromArgv(['--testdox' => true, '--no-progress' => true]);
        $this->printer = new ResultPrinter($this->output, $this->options);

        $this->printer->setTestCount(20);
        $feedbackFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'feedback1';
        file_put_contents($feedbackFile, 'EEE');

        $this->printer->printFeedback(new SplFileInfo($feedbackFile), []);
        $this->printer->printResults($this->getEmptyTestResult(), [], [new SplFileInfo($testdoxSource)]);

        self::assertSame($testdoxSourceContent, $this->output->fetch());
    }

    public function testPrintFeedbackFromMultilineSource(): void
    {
        $source = <<<'EOF'
        ...............................................................  63 / 300 ( 21%)
        ............................................................... 126 / 300 ( 42%)
        ............................................................... 189 / 300 ( 63%)
        ............................................................... 252 / 300 ( 84%)
        ................................................                300 / 300 (100%)
        
        EOF;

        $this->printer->setTestCount(300);
        $this->printer->start();
        $this->output->fetch();
        $feedbackFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'feedback1';
        file_put_contents($feedbackFile, $source);
        $this->printer->printFeedback(new SplFileInfo($feedbackFile), []);
        $contents = $this->output->fetch();
        self::assertSame($source, $contents);
    }

    private function getStartOutput(): string
    {
        $this->printer->start();

        return $this->output->fetch();
    }

    private function getEmptyTestResult(): TestResult
    {
        return new TestResult(
            0,
            0,
            0,
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
        );
    }
}
