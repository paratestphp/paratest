<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Logging\LogInterpreter;
use ParaTest\Parser\ParsedFunction;
use ParaTest\Tests\Unit\ResultTester;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;
use ParaTest\Runners\PHPUnit\Suite;
use ParaTest\Runners\PHPUnit\TestMethod;

class ResultPrinterTest extends ResultTester
{
    /**
     * @var ResultPrinter
     */
    protected $printer;

    /**
     * @var LogInterpreter
     */
    protected $interpreter;

    /**
     * @var Suite
     */
    protected $passingSuiteWithWrongTestCountEstimation;

    public function setUp(): void
    {
        parent::setUp();
        $this->interpreter = new LogInterpreter();
        $this->printer = new ResultPrinter($this->interpreter);
        $pathToConfig = $this->getPathToConfig();
        if (file_exists($pathToConfig)) {
            unlink($pathToConfig);
        }

        $this->passingSuiteWithWrongTestCountEstimation = $this->getSuiteWithResult('single-passing.xml', 1);
    }

    /**
     * @return string
     */
    protected function getPathToConfig()
    {
        return __DIR__ . DS . 'myconfig.xml';
    }

    public function testConstructor()
    {
        $this->assertEquals([], $this->getObjectValue($this->printer, 'suites'));
        $this->assertInstanceOf(
            LogInterpreter::class,
            $this->getObjectValue($this->printer, 'results')
        );
    }

    public function testAddTestShouldAddTest()
    {
        $suite = new Suite('/path/to/ResultSuite.php', []);

        $this->printer->addTest($suite);

        $this->assertEquals([$suite], $this->getObjectValue($this->printer, 'suites'));
    }

    public function testAddTestReturnsSelf()
    {
        $suite = new Suite('/path/to/ResultSuite.php', []);

        $self = $this->printer->addTest($suite);

        $this->assertSame($this->printer, $self);
    }

    public function testStartPrintsOptionInfo()
    {
        $options = new Options();
        $contents = $this->getStartOutput($options);
        $expected = sprintf(
            "\nRunning phpunit in %s processes with %s\n\n",
            Options::getNumberOfCPUCores(),
            $options->phpunit
        );
        $this->assertStringStartsWith($expected, $contents);
    }

    public function testStartSetsWidthAndMaxColumn()
    {
        $funcs = [];
        for ($i = 0; $i < 120; ++$i) {
            $funcs[] = new ParsedFunction('doc', 'public', 'function' . $i);
        }
        $suite = new Suite('/path', $funcs);
        $this->printer->addTest($suite);
        $this->getStartOutput(new Options());
        $numTestsWidth = $this->getObjectValue($this->printer, 'numTestsWidth');
        $maxColumn = $this->getObjectValue($this->printer, 'maxColumn');
        $this->assertEquals(3, $numTestsWidth);
        $this->assertEquals(63, $maxColumn);
    }

    public function testStartPrintsOptionInfoAndConfigurationDetailsIfConfigFilePresent()
    {
        $pathToConfig = $this->getPathToConfig();
        file_put_contents($pathToConfig, '<root />');
        $options = new Options(['configuration' => $pathToConfig]);
        $contents = $this->getStartOutput($options);
        $expected = sprintf(
            "\nRunning phpunit in %s processes with %s\n\nConfiguration read from %s\n\n",
            Options::getNumberOfCPUCores(),
            $options->phpunit,
            $pathToConfig
        );
        $this->assertStringStartsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithFunctionalMode()
    {
        $options = new Options(['functional' => true]);
        $contents = $this->getStartOutput($options);
        $expected = sprintf(
            "\nRunning phpunit in %s processes with %s. Functional mode is ON.\n\n",
            Options::getNumberOfCPUCores(),
            $options->phpunit
        );
        $this->assertStringStartsWith($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithSingularForOneProcess()
    {
        $options = new Options(['processes' => 1]);
        $contents = $this->getStartOutput($options);
        $expected = sprintf("\nRunning phpunit in 1 process with %s\n\n", $options->phpunit);
        $this->assertStringStartsWith($expected, $contents);
    }

    public function testAddSuiteAddsFunctionCountToTotalTestCases()
    {
        $suite = new Suite('/path', [
            new ParsedFunction('doc', 'public', 'funcOne'),
            new ParsedFunction('doc', 'public', 'funcTwo'),
        ]);
        $this->printer->addTest($suite);
        $this->assertEquals(2, $this->printer->getTotalCases());
    }

    public function testAddTestMethodIncrementsCountByOne()
    {
        $method = new TestMethod('/path', ['testThisMethod']);
        $this->printer->addTest($method);
        $this->assertEquals(1, $this->printer->getTotalCases());
    }

    public function testGetHeader()
    {
        $this->printer->addTest($this->errorSuite)
            ->addTest($this->failureSuite);

        $this->prepareReaders();

        $header = $this->printer->getHeader();

        $this->assertRegExp(
            "/\n\nTime: [0-9]+([.][0-9]{1,2})? " .
            '(minute|minutes|second|seconds|ms)?,' .
            " Memory:[\s][0-9]+([.][0-9]{1,2})? ?M[Bb]\n\n/",
            $header
        );
    }

    public function testGetErrorsSingleError()
    {
        $this->printer->addTest($this->errorSuite)
            ->addTest($this->failureSuite);

        $this->prepareReaders();

        $errors = $this->printer->getErrors();

        $eq = "There was 1 error:\n\n";
        $eq .= "1) UnitTestWithErrorTest::testTruth\n";
        $eq .= "Exception: Error!!!\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12\n";

        $this->assertEquals($eq, $errors);
    }

    public function testGetErrorsMultipleErrors()
    {
        $this->printer->addTest($this->errorSuite)
            ->addTest($this->otherErrorSuite);

        $this->prepareReaders();

        $errors = $this->printer->getErrors();

        $eq = "There were 2 errors:\n\n";
        $eq .= "1) UnitTestWithErrorTest::testTruth\n";
        $eq .= "Exception: Error!!!\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12\n";
        $eq .= "\n2) UnitTestWithOtherErrorTest::testSomeCase\n";
        $eq .= "Exception: Another Error!!!\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithOtherErrorTest.php:12\n";

        $this->assertEquals($eq, $errors);
    }

    public function testGetFailures()
    {
        $this->printer->addTest($this->mixedSuite);

        $this->prepareReaders();

        $failures = $this->printer->getFailures();

        $eq = "There were 2 failures:\n\n";
        $eq .= "1) UnitTestWithClassAnnotationTest::testFalsehood\n";
        $eq .= "Failed asserting that true is false.\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php:20\n";
        $eq .= "\n2) UnitTestWithMethodAnnotationsTest::testFalsehood\n";
        $eq .= "Failed asserting that true is false.\n\n";
        $eq .= "/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php:18\n";

        $this->assertEquals($eq, $failures);
    }

    public function testGetFooterWithFailures()
    {
        $this->printer->addTest($this->errorSuite)
            ->addTest($this->mixedSuite);

        $this->prepareReaders();

        $footer = $this->printer->getFooter();

        $eq = "\nFAILURES!\n";
        $eq .= "Tests: 8, Assertions: 6, Failures: 2, Errors: 2.\n";

        $this->assertEquals($eq, $footer);
    }

    public function testGetFooterWithSuccess()
    {
        $this->printer->addTest($this->passingSuite);

        $this->prepareReaders();

        $footer = $this->printer->getFooter();

        $eq = "OK (3 tests, 3 assertions)\n";

        $this->assertEquals($eq, $footer);
    }

    public function testPrintFeedbackForMixed()
    {
        $this->printer->addTest($this->mixedSuite);
        ob_start();
        $this->printer->printFeedback($this->mixedSuite);
        $contents = ob_get_clean();
        $this->assertEquals('.F.E.F.', $contents);
    }

    public function testPrintFeedbackForMoreThan100Suites()
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

        //assert it is as expected
        $expected = '';
        for ($i = 0; $i < 63; ++$i) {
            $expected .= '.';
        }
        $expected .= "  63 / 120 ( 52%)\n";
        for ($i = 0; $i < 57; ++$i) {
            $expected .= '.';
        }
        $this->assertEquals($expected, $feedback);
    }

    public function testResultPrinterAdjustsTotalCountForDataProviders()
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

        //assert it is as expected
        $expected = '';
        for ($i = 0; $i < 65; ++$i) {
            $expected .= '.';
        }
        $expected .= " 65 / 66 ( 98%)\n";
        for ($i = 0; $i < 1; ++$i) {
            $expected .= '.';
        }
        $this->assertEquals($expected, $feedback);
    }

    protected function getStartOutput(Options $options)
    {
        ob_start();
        $this->printer->start($options);
        $contents = ob_get_clean();

        return $contents;
    }

    private function prepareReaders()
    {
        $suites = $this->getObjectValue($this->printer, 'suites');
        ob_start();
        foreach ($suites as $suite) {
            $this->printer->printFeedback($suite);
        }
        ob_end_clean();
    }
}
