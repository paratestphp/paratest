<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Logging\LogInterpreter;
use ParaTest\Parser\ParsedFunction;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;
use ParaTest\Runners\PHPUnit\Suite;
use ParaTest\Runners\PHPUnit\TestMethod;
use ParaTest\Tests\Unit\ResultTester;

use function defined;
use function file_exists;
use function file_put_contents;
use function ob_end_clean;
use function ob_get_clean;
use function ob_start;
use function sprintf;
use function unlink;

class ResultPrinterTest extends ResultTester
{
    /** @var ResultPrinter */
    protected $printer;

    /** @var LogInterpreter */
    protected $interpreter;

    /** @var Suite */
    protected $passingSuiteWithWrongTestCountEstimation;

    public function setUp(): void
    {
        parent::setUp();
        $this->interpreter = new LogInterpreter();
        $this->printer     = new ResultPrinter($this->interpreter);
        $pathToConfig      = $this->getPathToConfig();
        if (file_exists($pathToConfig)) {
            unlink($pathToConfig);
        }

        $this->passingSuiteWithWrongTestCountEstimation = $this->getSuiteWithResult('single-passing.xml', 1);
    }

    protected function getPathToConfig(): string
    {
        return __DIR__ . DS . 'phpunit-myconfig.xml';
    }

    public function testConstructor(): void
    {
        $this->assertEquals([], $this->getObjectValue($this->printer, 'suites'));
        $this->assertInstanceOf(
            LogInterpreter::class,
            $this->getObjectValue($this->printer, 'results')
        );
    }

    public function testAddTestShouldAddTest(): void
    {
        $suite = new Suite('/path/to/ResultSuite.php', []);

        $this->printer->addTest($suite);

        $this->assertEquals([$suite], $this->getObjectValue($this->printer, 'suites'));
    }

    public function testAddTestReturnsSelf(): void
    {
        $suite = new Suite('/path/to/ResultSuite.php', []);

        $self = $this->printer->addTest($suite);

        $this->assertSame($this->printer, $self);
    }

    public function testStartPrintsOptionInfo(): void
    {
        $options  = new Options();
        $contents = $this->getStartOutput($options);
        $expected = sprintf(
            "\nRunning phpunit in %s processes with %s\n\n",
            Options::getNumberOfCPUCores(),
            $options->phpunit
        );
        $this->assertStringStartsWith($expected, $contents);
    }

    public function testStartSetsWidthAndMaxColumn(): void
    {
        $funcs = [];
        for ($i = 0; $i < 120; ++$i) {
            $funcs[] = new ParsedFunction('doc', 'public', 'function' . $i);
        }

        $suite = new Suite('/path', $funcs);
        $this->printer->addTest($suite);
        $this->getStartOutput(new Options());
        $numTestsWidth = $this->getObjectValue($this->printer, 'numTestsWidth');
        $this->assertEquals(3, $numTestsWidth);
        $maxExpectedColumun = 63;
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $maxExpectedColumun -= 1;
        }

        $maxColumn = $this->getObjectValue($this->printer, 'maxColumn');
        $this->assertEquals($maxExpectedColumun, $maxColumn);
    }

    public function testStartPrintsOptionInfoAndConfigurationDetailsIfConfigFilePresent(): void
    {
        $pathToConfig = $this->getPathToConfig();
        file_put_contents($pathToConfig, '<root />');
        $options  = new Options(['configuration' => $pathToConfig]);
        $contents = $this->getStartOutput($options);
        $expected = sprintf(
            "\nRunning phpunit in %s processes with %s\n\nConfiguration read from %s\n\n",
            Options::getNumberOfCPUCores(),
            $options->phpunit,
            $pathToConfig
        );
        $this->assertStringStartsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithFunctionalMode(): void
    {
        $options  = new Options(['functional' => true]);
        $contents = $this->getStartOutput($options);
        $expected = sprintf(
            "\nRunning phpunit in %s processes with %s. Functional mode is ON.\n\n",
            Options::getNumberOfCPUCores(),
            $options->phpunit
        );
        $this->assertStringStartsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithSingularForOneProcess(): void
    {
        $options  = new Options(['processes' => 1]);
        $contents = $this->getStartOutput($options);
        $expected = sprintf("\nRunning phpunit in 1 process with %s\n\n", $options->phpunit);
        $this->assertStringStartsWith($expected, $contents);
    }

    public function testAddSuiteAddsFunctionCountToTotalTestCases(): void
    {
        $suite = new Suite('/path', [
            new ParsedFunction('doc', 'public', 'funcOne'),
            new ParsedFunction('doc', 'public', 'funcTwo'),
        ]);
        $this->printer->addTest($suite);
        $this->assertEquals(2, $this->printer->getTotalCases());
    }

    public function testAddTestMethodIncrementsCountByOne(): void
    {
        $method = new TestMethod('/path', ['testThisMethod']);
        $this->printer->addTest($method);
        $this->assertEquals(1, $this->printer->getTotalCases());
    }

    public function testGetHeader(): void
    {
        $this->printer->addTest($this->errorSuite)
            ->addTest($this->failureSuite);

        $this->prepareReaders();

        $header = $this->printer->getHeader();

        $this->assertMatchesRegularExpression(
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

        $this->assertEquals($eq, $errors);
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

        $this->assertEquals($eq, $errors);
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

        $this->assertEquals($eq, $failures);
    }

    public function testGetFooterWithFailures(): void
    {
        $this->printer->addTest($this->errorSuite)
            ->addTest($this->mixedSuite);

        $this->prepareReaders();

        $footer = $this->printer->getFooter();

        $eq  = "\nFAILURES!\n";
        $eq .= "Tests: 8, Assertions: 6, Failures: 2, Errors: 2.\n";

        $this->assertEquals($eq, $footer);
    }

    public function testGetFooterWithSuccess(): void
    {
        $this->printer->addTest($this->passingSuite);

        $this->prepareReaders();

        $footer = $this->printer->getFooter();

        $eq = "OK (3 tests, 3 assertions)\n";

        $this->assertEquals($eq, $footer);
    }

    public function testPrintFeedbackForMixed(): void
    {
        $this->printer->addTest($this->mixedSuite);
        ob_start();
        $this->printer->printFeedback($this->mixedSuite);
        $contents = ob_get_clean();
        $this->assertEquals('.F.E.F.', $contents);
    }

    public function testPrintFeedbackForMoreThan100Suites(): void
    {
        //add tests
        for ($i = 0; $i < 40; ++$i) {
            $this->printer->addTest($this->passingSuite);
        }

        //start the printer so boundaries are established
        ob_start();
        $this->printer->start(new Options());
        ob_end_clean();

        //get the feedback string
        ob_start();
        for ($i = 0; $i < 40; ++$i) {
            $this->printer->printFeedback($this->passingSuite);
        }

        $feedback = ob_get_clean();

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

        $this->assertEquals($expected, $feedback);
    }

    public function testResultPrinterAdjustsTotalCountForDataProviders(): void
    {
        //add tests
        for ($i = 0; $i < 22; ++$i) {
            $this->printer->addTest($this->passingSuiteWithWrongTestCountEstimation);
        }

        //start the printer so boundaries are established
        ob_start();
        $this->printer->start(new Options());
        ob_end_clean();

        //get the feedback string
        ob_start();
        for ($i = 0; $i < 22; ++$i) {
            $this->printer->printFeedback($this->passingSuiteWithWrongTestCountEstimation);
        }

        $feedback = ob_get_clean();

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

        $this->assertEquals($expected, $feedback);
    }

    protected function getStartOutput(Options $options): string
    {
        ob_start();
        $this->printer->start($options);

        return ob_get_clean();
    }

    private function prepareReaders(): void
    {
        $suites = $this->getObjectValue($this->printer, 'suites');
        ob_start();
        foreach ($suites as $suite) {
            $this->printer->printFeedback($suite);
        }

        ob_end_clean();
    }
}
