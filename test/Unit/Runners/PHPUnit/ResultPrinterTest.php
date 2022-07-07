<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Logging\LogInterpreter;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;
use ParaTest\Runners\PHPUnit\Suite;
use ParaTest\Runners\PHPUnit\TestMethod;
use ParaTest\Tests\Unit\ResultTester;
use RuntimeException;
use SebastianBergmann\Environment\Runtime;
use Symfony\Component\Console\Output\BufferedOutput;

use function defined;
use function file_get_contents;
use function file_put_contents;
use function phpversion;
use function preg_match_all;
use function sprintf;
use function str_repeat;
use function uniqid;

use const PHP_VERSION;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\ResultPrinter
 */
final class ResultPrinterTest extends ResultTester
{
    /** @var ResultPrinter */
    private $printer;
    /** @var BufferedOutput */
    private $output;
    /** @var LogInterpreter */
    private $interpreter;
    /** @var Suite */
    private $passingSuiteWithWrongTestCountEstimation;
    /** @var Options */
    private $options;

    protected function setUpInterpreter(): void
    {
        $this->interpreter = new LogInterpreter();
        $this->output      = new BufferedOutput();
        $this->options     = $this->createOptionsFromArgv(['--verbose' => true], __DIR__);
        $this->printer     = new ResultPrinter($this->interpreter, $this->output, $this->options);

        $this->passingSuiteWithWrongTestCountEstimation = $this->getSuiteWithResult('single-passing.xml', 1);
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

        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv(['--verbose' => true]));
        $contents      = $this->getStartOutput();

        static::assertStringContainsString(sprintf("Runtime:       PHP %s\n", PHP_VERSION), $contents);
    }

    public function testStartPrintsRuntimeInfosWithCcDriver(): void
    {
        if (! (new Runtime())->hasPCOV()) {
            $this->markTestSkipped('PCOV not loaded');
        }

        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv([
            '--verbose' => true,
            '--coverage-text' => 'php://stdout',
        ]));
        $contents      = $this->getStartOutput();

        static::assertStringContainsString(sprintf("Runtime:       PHP %s with PCOV %s\n", PHP_VERSION, phpversion('pcov')), $contents);
    }

    public function testStartSetsWidthAndMaxColumn(): void
    {
        $funcs = [];
        for ($i = 0; $i < 120; ++$i) {
            $funcs[] = new TestMethod((string) $i, ['testMe'], false, false, $this->tmpDir);
        }

        $suite = new Suite('/path', $funcs, false, false, $this->tmpDir);
        $this->printer->addTest($suite);
        $this->getStartOutput();
        $numTestsWidth = $this->getObjectValue($this->printer, 'numTestsWidth');
        static::assertSame(3, $numTestsWidth);
        $maxExpectedColumun = 63;
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $maxExpectedColumun -= 1;
        }

        $maxColumn = $this->getObjectValue($this->printer, 'maxColumn');
        static::assertSame($maxExpectedColumun, $maxColumn);
    }

    public function testStartPrintsOptionInfoAndConfigurationDetailsIfConfigFilePresent(): void
    {
        $pathToConfig = $this->tmpDir . DS . 'phpunit-myconfig.xml';

        file_put_contents($pathToConfig, '<root />');
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv([
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

        file_put_contents($pathToConfig, '<root />');
        $random_seed   = 1234;
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv([
            '--configuration' => $pathToConfig,
            '--order-by' => Options::ORDER_RANDOM,
            '--random-order-seed' => (string) $random_seed,
            '--verbose' => true,
        ]));
        $contents      = $this->getStartOutput();
        $expected      = sprintf("Random Seed:   %s\n\n", $random_seed);

        static::assertStringEndsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithFunctionalMode(): void
    {
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv([
            '--functional' => true,
            '--verbose' => true,
        ]));
        $contents      = $this->getStartOutput();
        $expected      = sprintf("Processes:     %s. Functional mode is ON.\n", $this->options->processes());
        static::assertStringStartsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithSingularForOneProcess(): void
    {
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv([
            '--processes' => '1',
            '--verbose' => true,
        ]));
        $contents      = $this->getStartOutput();

        static::assertStringStartsWith("Processes:     1\n", $contents);
    }

    public function testAddSuiteAddsFunctionCountToTotalTestCases(): void
    {
        $suite = new Suite('/path', [
            new TestMethod('funcOne', ['testMe'], false, false, $this->tmpDir),
            new TestMethod('funcTwo', ['testMe'], false, false, $this->tmpDir),
        ], false, false, $this->tmpDir);
        $this->printer->addTest($suite);
        static::assertSame(2, $this->printer->getTotalCases());
    }

    public function testAddTestMethodIncrementsCountByOne(): void
    {
        $method = new TestMethod('/path', ['testThisMethod'], false, false, $this->tmpDir);
        $this->printer->addTest($method);
        static::assertSame(1, $this->printer->getTotalCases());
    }

    public function testGetHeader(): void
    {
        $this->prepareReaders(
            $this->errorSuite,
            $this->failureSuite
        );

        $header = $this->printer->getHeader();

        static::assertMatchesRegularExpression(
            "/\nTime: ([.:]?[0-9]{1,3})+ ?" .
            '(minute|minutes|second|seconds|ms|)?,' .
            " Memory:[\\s][0-9]+([.][0-9]{1,2})? ?M[Bb]\n\n/",
            $header
        );
    }

    public function testGetErrorsSingleError(): void
    {
        $this->prepareReaders(
            $this->errorSuite,
            $this->failureSuite
        );

        $errors = $this->printer->getErrors();

        $eq  = "There was 1 error:\n\n";
        $eq .= "1) ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest::testTruth\n";
        $eq .= "RuntimeException: Error!!!\n\n";
        $eq .= "./test/fixtures/failing_tests/UnitTestWithErrorTest.php:21\n\n";

        static::assertSame($eq, $errors);
    }

    public function testGetErrorsMultipleErrors(): void
    {
        $this->prepareReaders(
            $this->errorSuite,
            $this->errorSuite
        );

        $errors = $this->printer->getErrors();

        $eq  = "There were 2 errors:\n";
        $eq .= "\n";
        $eq .= "1) ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest::testTruth\n";
        $eq .= "RuntimeException: Error!!!\n";
        $eq .= "\n";
        $eq .= "./test/fixtures/failing_tests/UnitTestWithErrorTest.php:21\n";
        $eq .= "\n";
        $eq .= "2) ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest::testTruth\n";
        $eq .= "RuntimeException: Error!!!\n";
        $eq .= "\n";
        $eq .= "./test/fixtures/failing_tests/UnitTestWithErrorTest.php:21\n";
        $eq .= "\n";

        static::assertSame($eq, $errors);
    }

    public function testGetFailures(): void
    {
        $this->prepareReaders(
            $this->mixedSuite
        );

        $failures = $this->printer->getFailures();

        $expected =
            "There were 3 failures:\n"
            . "\n"
            . "1) ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithClassAnnotationTest::testFalsehood\n"
            . "Failed asserting that true is false.\n"
            . "\n"
            . "./test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php:32\n"
            . "\n"
            . "2) ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest::testFalsehood\n"
            . "Failed asserting that two strings are identical.\n"
            . "--- Expected\n"
            . "+++ Actual\n"
            . "@@ @@\n"
            . "-'foo'\n"
            . "+'bar'\n"
            . "\n"
            . "./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:27\n"
            . "\n"
            . "3) ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest::testFalsehood\n"
            . "Failed asserting that two strings are identical.\n"
            . "--- Expected\n"
            . "+++ Actual\n"
            . "@@ @@\n"
            . "-'foo'\n"
            . "+'bar'\n"
            . "\n"
            . "./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:27\n"
            . "\n";

        static::assertSame($expected, $failures);
    }

    public function testGetRisky(): void
    {
        $this->prepareReaders(
            $this->mixedSuite
        );

        $failures = $this->printer->getRisky();

        $eq  = "There were 2 riskys:\n\n";
        $eq .= "1) ParaTest\\Tests\\fixtures\\failing_tests\UnitTestWithErrorTest::testRisky\n";
        $eq .= "This test did not perform any assertions\n\n";
        $eq .= "./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:66\n\n";
        $eq .= "2) ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest::testRisky\n";
        $eq .= "This test did not perform any assertions\n\n";
        $eq .= "./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:66\n\n";

        static::assertSame($eq, $failures);
    }

    public function testGetSkipped(): void
    {
        $this->prepareReaders(
            $this->skipped
        );

        $failures = $this->printer->getSkipped();

        $eq  = "There was 1 skipped:\n\n";
        $eq .= "1) ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest::testSkipped\n\n";
        $eq .= "./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:50\n\n";

        static::assertSame($eq, $failures);
    }

    public function testGetFooterWithFailures(): void
    {
        $this->prepareReaders(
            $this->errorSuite,
            $this->mixedSuite
        );

        $footer = $this->printer->getFooter();

        $eq  = "FAILURES!\n";
        $eq .= "Tests: 20, Assertions: 10, Errors: 4, Failures: 3, Warnings: 2, Skipped: 4.\n";

        static::assertSame($eq, $footer);
    }

    public function testGetFooterWithWarnings(): void
    {
        $this->prepareReaders(
            $this->warningSuite
        );

        $footer = $this->printer->getFooter();

        $eq  = "WARNINGS!\n";
        $eq .= "Tests: 1, Assertions: 0, Warnings: 1.\n";

        static::assertSame($eq, $footer);
    }

    public function testGetFooterWithSuccess(): void
    {
        $this->prepareReaders(
            $this->passingSuite
        );

        $footer = $this->printer->getFooter();

        $eq = "OK (3 tests, 3 assertions)\n";

        static::assertSame($eq, $footer);
    }

    public function testPrintFeedbackForMixed(): void
    {
        $this->printer->addTest($this->mixedSuite);
        $this->printer->printFeedback($this->mixedSuite);
        $contents = $this->output->fetch();
        static::assertSame("EEEWWFFFRRSSSS..... 19 / 19 (100%)\n", $contents);
    }

    public function testPrintFeedbackForMoreThan100Suites(): void
    {
        //add tests
        for ($i = 0; $i < 40; ++$i) {
            $this->printer->addTest($this->passingSuite);
        }

        $this->printer->start();
        $this->output->fetch();

        for ($i = 0; $i < 40; ++$i) {
            $this->printer->printFeedback($this->passingSuite);
        }

        $feedback = $this->output->fetch();

        $firstRowColumns  = 63;
        $secondRowColumns = 57;
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $firstRowColumns  -= 1;
            $secondRowColumns += 1;
        }

        //assert it is as expected
        $expected = '';
        for ($i = 0; $i < $firstRowColumns; ++$i) {
            $expected .= '.';
        }

        $expected .= sprintf("  %s / 120 ( %s%%)\n", $firstRowColumns, (int) ($firstRowColumns / 120 * 100));
        for ($i = 0; $i < $secondRowColumns; ++$i) {
            $expected .= '.';
        }

        $expected .= sprintf("%s 120 / 120 (100%%)\n", str_repeat(' ', $firstRowColumns - $secondRowColumns));

        static::assertSame($expected, $feedback);
    }

    public function testResultPrinterAdjustsTotalCountForDataProviders(): void
    {
        //add tests
        for ($i = 0; $i < 22; ++$i) {
            $this->printer->addTest($this->passingSuiteWithWrongTestCountEstimation);
        }

        $this->printer->start();
        $this->output->fetch();

        for ($i = 0; $i < 22; ++$i) {
            $this->printer->printFeedback($this->passingSuiteWithWrongTestCountEstimation);
        }

        $feedback = $this->output->fetch();

        $firstRowColumns  = 65;
        $secondRowColumns = 1;
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $firstRowColumns  -= 1;
            $secondRowColumns += 1;
        }

        //assert it is as expected
        $expected = '';
        for ($i = 0; $i < $firstRowColumns; ++$i) {
            $expected .= '.';
        }

        $expected .= sprintf(" %s / 66 ( %s%%)\n", $firstRowColumns, (int) ($firstRowColumns / 66 * 100));
        for ($i = 0; $i < $secondRowColumns; ++$i) {
            $expected .= '.';
        }

        $expected .= sprintf("%s 66 / 66 (100%%)\n", str_repeat(' ', $firstRowColumns - $secondRowColumns));

        static::assertSame($expected, $feedback);
    }

    public function testColorsForFailing(): void
    {
        $this->options = $this->createOptionsFromArgv(['--colors' => true]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->mixedSuite);

        $this->printer->start();
        $this->printer->printFeedback($this->mixedSuite);
        $this->printer->printResults();

        static::assertStringContainsString('FAILURES', $this->output->fetch());
    }

    public function testColorsForWarning(): void
    {
        $this->options = $this->createOptionsFromArgv(['--colors' => true]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->warningSuite);

        $this->printer->start();
        $this->printer->printFeedback($this->warningSuite);
        $this->printer->printResults();

        static::assertStringContainsString('WARNING', $this->output->fetch());
    }

    public function testColorsForSkipped(): void
    {
        $this->options = $this->createOptionsFromArgv(['--colors' => true]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->skipped);

        $this->printer->start();
        $this->printer->printFeedback($this->skipped);
        $this->printer->printResults();

        $output = $this->output->fetch();
        static::assertStringContainsString('OK', $output);
        static::assertStringNotContainsString('UnitTestWithMethodAnnotationsTest', $output);
    }

    public function testColorsParsing(): void
    {
        $this->options = $this->createOptionsFromArgv(['--colors' => true, '--verbose' => true]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->otherFailureSuite);

        $this->printer->start();
        $this->printer->printFeedback($this->otherFailureSuite);
        $this->printer->printResults();

        $output = $this->output->fetch();
        static::assertStringContainsString('FAILURES', $output);
        static::assertStringContainsString('FailingSymfonyOutputCollisionTest', $output);
        static::assertStringContainsString('<bg=%s>', $output);
    }

    public function testSkippedOutpusMessagesWithVerbose(): void
    {
        $this->options = $this->createOptionsFromArgv(['--colors' => true, '--verbose' => true]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->skipped);

        $this->printer->start();
        $this->printer->printFeedback($this->skipped);
        $this->printer->printResults();

        $output = $this->output->fetch();
        static::assertStringContainsString('OK', $output);
        static::assertStringContainsString('UnitTestWithMethodAnnotationsTest', $output);
    }

    public function testColorsForPassing(): void
    {
        $this->options = $this->createOptionsFromArgv(['--colors' => true, '--verbose' => false]);
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $this->printer->addTest($this->passingSuite);

        $this->printer->start();
        $this->printer->printFeedback($this->passingSuite);
        $this->printer->printResults();

        static::assertStringContainsString('OK', $this->output->fetch());
    }

    /**
     * This test ensure Code Coverage over printSkippedAndIncomplete
     * but the real case for this test case is missing at the time of writing
     *
     * @see \ParaTest\Runners\PHPUnit\ResultPrinter::printSkippedAndIncomplete
     */
    public function testParallelSuiteProgressOverhead(): void
    {
        $suite = $this->getSuiteWithResult('mixed-results.xml', 100);
        $this->printer->addTest($suite);

        $this->printer->start();
        $this->printer->printFeedback($suite);
        $this->printer->printResults();

        static::assertStringContainsString('FAILURES', $this->output->fetch());
    }

    public function testEmptyLogFileRaiseException(): void
    {
        $test = new ExecutableTestChild(uniqid(), false, false, $this->tmpDir);

        $this->expectException(RuntimeException::class);

        $this->printer->printFeedback($test);
    }

    public function testTeamcityEmptyLogFileRaiseException(): void
    {
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
   │ ./test/fixtures/failing_tests/UnitTestWithErrorTest.php:21
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
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:27
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
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:27
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
   │ ./test/fixtures/failing_tests/UnitTestWithErrorTest.php:21
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
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:27
   │

 ✔ Array length [1234.57 ms]
 ⚠ Warning [1234.57 ms]
   │
   │ MyWarning
   │

 ↩ Skipped [1234.57 ms]
   │
   │ ./test/fixtures/failing_tests/UnitTestWithErrorTest.php:50
   │

 ↩ Incomplete [1234.57 ms]
   │
   │ ./test/fixtures/failing_tests/UnitTestWithErrorTest.php:58
   │

 ☢ Risky [1234.57 ms]
   │
   │ This test did not perform any assertions
   │
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:66
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
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:27
   │

 ✔ Array length [1234.57 ms]
 ⚠ Warning [1234.57 ms]
   │
   │ MyWarning
   │

 ↩ Skipped [1234.57 ms]
   │
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:50
   │

 ↩ Incomplete [1234.57 ms]
   │
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:58
   │

 ☢ Risky [1234.57 ms]
   │
   │ This test did not perform any assertions
   │
   │ ./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:66
   │


EOF;

        static::assertSame($expected, $contents);
    }

    private function getStartOutput(): string
    {
        $this->printer->start();

        return $this->output->fetch();
    }

    private function prepareReaders(ExecutableTest ...$executableTests): void
    {
        self::assertNotSame([], $executableTests);

        foreach ($executableTests as $suite) {
            $this->printer->addTest($suite);
        }

        foreach ($executableTests as $suite) {
            $this->printer->printFeedback($suite);
        }

        $this->output->fetch();
    }
}
