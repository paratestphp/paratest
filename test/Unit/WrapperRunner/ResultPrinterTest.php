<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\WrapperRunner;

use ParaTest\Options;
use ParaTest\Tests\TestBase;
use ParaTest\WrapperRunner\ResultPrinter;
use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Configuration\Configuration;
use RuntimeException;
use SebastianBergmann\Environment\Runtime;
use SplFileInfo;
use Symfony\Component\Console\Output\BufferedOutput;

use function file_get_contents;
use function file_put_contents;
use function phpversion;
use function preg_match_all;
use function sprintf;

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

        static::assertStringStartsWith($expected, $contents);
    }

    public function testStartPrintsRuntimeInfosWithoutCcDriver(): void
    {
        if ((new Runtime())->hasPCOV()) {
            $this->markTestSkipped('PCOV loaded');
        }

        $this->printer = new ResultPrinter($this->output, $this->createOptionsFromArgv(['--verbose' => true]));
        $contents      = $this->getStartOutput();

        static::assertStringContainsString(sprintf("Runtime:       PHP %s\n", PHP_VERSION), $contents);
    }

    public function testStartPrintsRuntimeInfosWithCcDriver(): void
    {
        if (! (new Runtime())->hasPCOV()) {
            $this->markTestSkipped('PCOV not loaded');
        }

        $this->printer = new ResultPrinter($this->output, $this->createOptionsFromArgv([
            '--verbose' => true,
            '--coverage-text' => 'php://stdout',
        ]));
        $contents      = $this->getStartOutput();

        static::assertStringContainsString(sprintf("Runtime:       PHP %s with PCOV %s\n", PHP_VERSION, phpversion('pcov')), $contents);
    }

    public function testStartPrintsOptionInfoAndConfigurationDetailsIfConfigFilePresent(): void
    {
        $pathToConfig = $this->tmpDir . DS . 'phpunit-myconfig.xml';

        file_put_contents($pathToConfig, '<phpunit />');
        $this->printer = new ResultPrinter($this->output, $this->createOptionsFromArgv([
            '--configuration' => $pathToConfig,
            '--verbose' => true,
        ]));
        $contents      = $this->getStartOutput();
        $expected      = sprintf("Configuration: %s\n\n", $pathToConfig);
        static::assertStringEndsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithRandom(): void
    {
        $pathToConfig = $this->tmpDir . DS . 'phpunit-myconfig.xml';

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

        static::assertStringEndsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithSingularForOneProcess(): void
    {
        $this->printer = new ResultPrinter($this->output, $this->createOptionsFromArgv([
            '--processes' => '1',
            '--verbose' => true,
        ]));
        $contents      = $this->getStartOutput();

        static::assertStringStartsWith("Processes:     1\n", $contents);
    }

    public function testGetHeader(): void
    {
        $this->printer->printResults(new TestResult(0, 0, 0, [], [], [], [], [], [], [], [], [], [], [], [], [], [], [], [], []));

        static::assertMatchesRegularExpression(
            "/\nTime: ([.:]?[0-9]{1,3})+ ?" .
            '(minute|minutes|second|seconds|ms|)?,' .
            " Memory:[\\s][0-9]+([.][0-9]{1,2})? ?M[Bb]\n\n/",
            $this->output->fetch(),
        );
    }

    public function testPrintFeedbackForMixed(): void
    {
        $this->printer->setTestCount(20);
        $feedbackFile = $this->tmpDir . DS . 'feedback1';
        file_put_contents($feedbackFile, 'EWWFFFRRSSSS....... 19 / 19 (100%)');
        $this->printer->printFeedback(new SplFileInfo($feedbackFile));
        $contents = $this->output->fetch();
        static::assertSame('EWWFFFRRSSSS.......', $contents);

        $feedbackFile = $this->tmpDir . DS . 'feedback2';
        file_put_contents($feedbackFile, 'E 1 / 1 (100%)');
        $this->printer->printFeedback(new SplFileInfo($feedbackFile));
        $contents = $this->output->fetch();
        static::assertSame("E 20 / 20 (100%)\n", $contents);
    }

    public function testColorsForFailing(): void
    {
        $this->options = $this->createOptionsFromArgv(['--colors' => Configuration::COLOR_ALWAYS]);
        $this->printer = new ResultPrinter($this->output, $this->options);
        $this->printer->setTestCount(20);
        $feedbackFile = $this->tmpDir . DS . 'feedback1';
        file_put_contents($feedbackFile, 'E');
        $this->printer->printFeedback(new SplFileInfo($feedbackFile));
        $contents = $this->output->fetch();
        static::assertStringContainsString('E', $contents);
        static::assertStringContainsString('31;1', $contents);
    }

    public function testTeamcityEmptyLogFileRaiseException(): void
    {
        self::markTestIncomplete();
        $teamcityLog = $this->tmpDir . DS . 'teamcity.log';

        $this->options = $this->createOptionsFromArgv(['--log-teamcity' => $teamcityLog]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);

        $test = $this->getSuiteWithResult('single-passing.xml', 1);

        file_put_contents($test->getTeamcityTempFile(), '');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Teamcity/');

        $this->printer->printFeedback($test);
    }

    public function testTeamcityFeedbackOnFile(): void
    {
        self::markTestIncomplete();
        $teamcityLog = $this->tmpDir . DS . 'teamcity2.log';

        $this->options = $this->createOptionsFromArgv(['--log-teamcity' => $teamcityLog]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->passingSuite);

        $this->printer->start();
        $this->printer->printFeedback($this->passingSuite);
        $this->printer->printResults();

        static::assertStringContainsString('OK', $this->output->fetch());
        static::assertFileExists($teamcityLog);

        $logContent = file_get_contents($teamcityLog);

        self::assertNotFalse($logContent);
        self::assertSame(9, preg_match_all('/^##teamcity/m', $logContent));
    }

    public function testTeamcityFeedbackOnStdout(): void
    {
        self::markTestIncomplete();
        $this->options = $this->createOptionsFromArgv(['--teamcity' => true]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->passingSuite);

        $this->printer->start();
        $this->printer->printFeedback($this->passingSuite);
        $this->printer->printResults();

        $output = $this->output->fetch();
        static::assertStringContainsString('OK', $output);
        self::assertSame(9, preg_match_all('/^##teamcity/m', $output));
    }

    public function testTestdoxOutputNonVerbose(): void
    {
        self::markTestIncomplete();
        $options = $this->createOptionsFromArgv(['--testdox' => true, '--verbose' => false]);
        $printer = new ResultPrinter($this->interpreter, $this->output, $options);
        $printer->printFeedback($this->mixedSuite);
        $contents = $this->output->fetch();

        $expected = <<<'EOF'
Unit Test With Class Annotation (ParaTest\Tests\fixtures\failing_tests\UnitTestWithClassAnnotation)
 ✔ Truth
 ✘ Falsehood
   │
   │ Failed asserting that true is false.
   │
   │ ./test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php:32
   │

 ✔ Array length
 ✔ Its a test

Unit Test With Error (ParaTest\Tests\fixtures\failing_tests\UnitTestWithError)
 ✘ Truth
   │
   │ RuntimeException: Error!!!
   │
   │ ./test/fixtures/failing_tests/UnitTestWithErrorTest.php:19
   │

 ✔ Is it false
 ✘ Falsehood
   │
   │ Failed asserting that two strings are identical.
   │ --- Expected
   │ +++ Actual
   │ @@ @@
   │ -'foo'
   │ +'bar'
   │
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:21
   │

 ✔ Array length
 ⚠ Warning
   │
   │ MyWarning
   │

 ↩ Skipped
 ↩ Incomplete
 ☢ Risky

Unit Test With Method Annotations (ParaTest\Tests\fixtures\failing_tests\UnitTestWithMethodAnnotations)
 ✔ Truth
 ✘ Falsehood
   │
   │ Failed asserting that two strings are identical.
   │ --- Expected
   │ +++ Actual
   │ @@ @@
   │ -'foo'
   │ +'bar'
   │
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:21
   │

 ✔ Array length
 ⚠ Warning
   │
   │ MyWarning
   │

 ↩ Skipped
 ↩ Incomplete
 ☢ Risky


EOF;

        static::assertSame($expected, $contents);
    }

    public function testTestdoxOutputVerbose(): void
    {
        self::markTestIncomplete();
        $options = $this->createOptionsFromArgv(['--testdox' => true, '--verbose' => true]);
        $printer = new ResultPrinter($this->interpreter, $this->output, $options);
        $printer->printFeedback($this->mixedSuite);
        $contents = $this->output->fetch();

        $expected = <<<'EOF'
Unit Test With Class Annotation (ParaTest\Tests\fixtures\failing_tests\UnitTestWithClassAnnotation)
 ✔ Truth [1234.57 ms]
 ✘ Falsehood [1234.57 ms]
   │
   │ Failed asserting that true is false.
   │
   │ ./test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php:32
   │

 ✔ Array length [1234.57 ms]
 ✔ Its a test [1234.57 ms]

Unit Test With Error (ParaTest\Tests\fixtures\failing_tests\UnitTestWithError)
 ✘ Truth [1234.57 ms]
   │
   │ RuntimeException: Error!!!
   │
   │ ./test/fixtures/failing_tests/UnitTestWithErrorTest.php:19
   │

 ✔ Is it false [1234.57 ms]
 ✘ Falsehood [1234.57 ms]
   │
   │ Failed asserting that two strings are identical.
   │ --- Expected
   │ +++ Actual
   │ @@ @@
   │ -'foo'
   │ +'bar'
   │
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:21
   │

 ✔ Array length [1234.57 ms]
 ⚠ Warning [1234.57 ms]
   │
   │ MyWarning
   │

 ↩ Skipped [1234.57 ms]
   │
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:39
   │

 ↩ Incomplete [1234.57 ms]
   │
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:45
   │

 ☢ Risky [1234.57 ms]
   │
   │ This test did not perform any assertions
   │

Unit Test With Method Annotations (ParaTest\Tests\fixtures\failing_tests\UnitTestWithMethodAnnotations)
 ✔ Truth [1234.57 ms]
 ✘ Falsehood [1234.57 ms]
   │
   │ Failed asserting that two strings are identical.
   │ --- Expected
   │ +++ Actual
   │ @@ @@
   │ -'foo'
   │ +'bar'
   │
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:21
   │

 ✔ Array length [1234.57 ms]
 ⚠ Warning [1234.57 ms]
   │
   │ MyWarning
   │

 ↩ Skipped [1234.57 ms]
   │
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:39
   │

 ↩ Incomplete [1234.57 ms]
   │
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:45
   │

 ☢ Risky [1234.57 ms]
   │
   │ This test did not perform any assertions
   │


EOF;

        static::assertSame($expected, $contents);
    }

    private function getStartOutput(): string
    {
        $this->printer->start();

        return $this->output->fetch();
    }
}
