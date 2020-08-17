<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Logging\LogInterpreter;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;
use ParaTest\Runners\PHPUnit\Suite;
use ParaTest\Runners\PHPUnit\TestMethod;
use ParaTest\Tests\Unit\ResultTester;
use Symfony\Component\Console\Output\BufferedOutput;

use function defined;
use function file_exists;
use function file_put_contents;
use function sprintf;
use function unlink;

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
        $this->options     = $this->createOptionsFromArgv([]);
        $this->printer     = new ResultPrinter($this->interpreter, $this->output, $this->options);
        $pathToConfig      = $this->getPathToConfig();
        if (file_exists($pathToConfig)) {
            unlink($pathToConfig);
        }

        $this->passingSuiteWithWrongTestCountEstimation = $this->getSuiteWithResult('single-passing.xml', 1);
    }

    private function getPathToConfig(): string
    {
        return __DIR__ . DS . 'phpunit-myconfig.xml';
    }

    public function testConstructor(): void
    {
        static::assertEquals([], $this->getObjectValue($this->printer, 'suites'));
        static::assertInstanceOf(
            LogInterpreter::class,
            $this->getObjectValue($this->printer, 'results')
        );
    }

    public function testAddTestShouldAddTest(): void
    {
        $suite = new Suite('/path/to/ResultSuite.php', [], false);

        $this->printer->addTest($suite);

        static::assertEquals([$suite], $this->getObjectValue($this->printer, 'suites'));
    }

    public function testAddTestReturnsSelf(): void
    {
        $suite = new Suite('/path/to/ResultSuite.php', [], false);

        $self = $this->printer->addTest($suite);

        static::assertSame($this->printer, $self);
    }

    public function testStartPrintsOptionInfo(): void
    {
        $contents = $this->getStartOutput();
        $expected = sprintf(
            "\nRunning phpunit in %s processes with %s\n\n",
            Options::getNumberOfCPUCores(),
            $this->options->phpunit()
        );
        static::assertStringStartsWith($expected, $contents);
    }

    public function testStartSetsWidthAndMaxColumn(): void
    {
        $funcs = [];
        for ($i = 0; $i < 120; ++$i) {
            $funcs[] = new TestMethod((string) $i, [], false);
        }

        $suite = new Suite('/path', $funcs, false);
        $this->printer->addTest($suite);
        $this->getStartOutput();
        $numTestsWidth = $this->getObjectValue($this->printer, 'numTestsWidth');
        static::assertEquals(3, $numTestsWidth);
        $maxExpectedColumun = 63;
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $maxExpectedColumun -= 1;
        }

        $maxColumn = $this->getObjectValue($this->printer, 'maxColumn');
        static::assertEquals($maxExpectedColumun, $maxColumn);
    }

    public function testStartPrintsOptionInfoAndConfigurationDetailsIfConfigFilePresent(): void
    {
        $pathToConfig = $this->getPathToConfig();
        file_put_contents($pathToConfig, '<root />');
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv(['--configuration' => $pathToConfig]));
        $contents      = $this->getStartOutput();
        $expected      = sprintf(
            "\nRunning phpunit in %s processes with %s\n\nConfiguration read from %s\n\n",
            Options::getNumberOfCPUCores(),
            $this->options->phpunit(),
            $pathToConfig
        );
        static::assertStringStartsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithFunctionalMode(): void
    {
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv(['--functional' => true]));
        $contents      = $this->getStartOutput();
        $expected      = sprintf(
            "\nRunning phpunit in %s processes with %s. Functional mode is ON.\n\n",
            Options::getNumberOfCPUCores(),
            $this->options->phpunit()
        );
        static::assertStringStartsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithSingularForOneProcess(): void
    {
        $this->printer = new ResultPrinter($this->interpreter, $this->output, $this->createOptionsFromArgv(['--processes' => 1]));
        $contents      = $this->getStartOutput();
        $expected      = sprintf("\nRunning phpunit in 1 process with %s\n\n", $this->options->phpunit());
        static::assertStringStartsWith($expected, $contents);
    }

    public function testAddSuiteAddsFunctionCountToTotalTestCases(): void
    {
        $suite = new Suite('/path', [
            new TestMethod('funcOne', [], false),
            new TestMethod('funcTwo', [], false),
        ], false);
        $this->printer->addTest($suite);
        static::assertEquals(2, $this->printer->getTotalCases());
    }

    public function testAddTestMethodIncrementsCountByOne(): void
    {
        $method = new TestMethod('/path', ['testThisMethod'], false);
        $this->printer->addTest($method);
        static::assertEquals(1, $this->printer->getTotalCases());
    }

    public function testGetHeader(): void
    {
        $this->printer->addTest($this->errorSuite)
            ->addTest($this->failureSuite);

        $this->prepareReaders();

        $header = $this->printer->getHeader();

        static::assertMatchesRegularExpression(
            "/\n\nTime: ([.:]?[0-9]{1,3})+ ?" .
            '(minute|minutes|second|seconds|ms|)?,' .
            " Memory:[\s][0-9]+([.][0-9]{1,2})? ?M[Bb]\n\n/",
            $header
        );
    }

    public function testGetErrorsSingleError(): void
    {
        $this->printer->addTest($this->errorSuite)
            ->addTest($this->failureSuite);

        $this->prepareReaders();

        $errors = $this->printer->getErrors();

        $eq  = "There was 1 error:\n\n";
        $eq .= "1) UnitTestWithErrorTest::testTruth\n";
        $eq .= "Exception: Error!!!\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12\n";

        static::assertEquals($eq, $errors);
    }

    public function testGetErrorsMultipleErrors(): void
    {
        $this->printer->addTest($this->errorSuite)
            ->addTest($this->otherErrorSuite);

        $this->prepareReaders();

        $errors = $this->printer->getErrors();

        $eq  = "There were 2 errors:\n\n";
        $eq .= "1) UnitTestWithErrorTest::testTruth\n";
        $eq .= "Exception: Error!!!\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12\n";
        $eq .= "\n2) UnitTestWithOtherErrorTest::testSomeCase\n";
        $eq .= "Exception: Another Error!!!\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithOtherErrorTest.php:12\n";

        static::assertEquals($eq, $errors);
    }

    public function testGetFailures(): void
    {
        $this->printer->addTest($this->mixedSuite);

        $this->prepareReaders();

        $failures = $this->printer->getFailures();

        $eq  = "There were 2 failures:\n\n";
        $eq .= "1) UnitTestWithClassAnnotationTest::testFalsehood\n";
        $eq .= "Failed asserting that true is false.\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php:20\n";
        $eq .= "\n2) UnitTestWithMethodAnnotationsTest::testFalsehood\n";
        $eq .= "Failed asserting that true is false.\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php:18\n";

        static::assertEquals($eq, $failures);
    }

    public function testGetFooterWithFailures(): void
    {
        $this->printer->addTest($this->errorSuite)
            ->addTest($this->mixedSuite);

        $this->prepareReaders();

        $footer = $this->printer->getFooter();

        $eq  = "\nFAILURES!\n";
        $eq .= "Tests: 8, Assertions: 6, Failures: 2, Errors: 2.\n";

        static::assertEquals($eq, $footer);
    }

    public function testGetFooterWithSuccess(): void
    {
        $this->printer->addTest($this->passingSuite);

        $this->prepareReaders();

        $footer = $this->printer->getFooter();

        $eq = "OK (3 tests, 3 assertions)\n";

        static::assertEquals($eq, $footer);
    }

    public function testPrintFeedbackForMixed(): void
    {
        $this->printer->addTest($this->mixedSuite);
        $this->printer->printFeedback($this->mixedSuite);
        $contents = $this->output->fetch();
        static::assertEquals('.F.E.F.', $contents);
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

        static::assertEquals($expected, $feedback);
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

        static::assertEquals($expected, $feedback);
    }

    private function getStartOutput(): string
    {
        $this->printer->start();

        return $this->output->fetch();
    }

    private function prepareReaders(): void
    {
        $suites = $this->getObjectValue($this->printer, 'suites');
        foreach ($suites as $suite) {
            $this->printer->printFeedback($suite);
        }

        $this->output->fetch();
    }
}
