<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Logging\JUnit;

use InvalidArgumentException;
use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\TestSuite;
use ParaTest\Tests\TestBase;
use PHPUnit\Framework\ExpectationFailedException;
use stdClass;

use function file_get_contents;
use function file_put_contents;

final class ReaderTest extends TestBase
{
    /** @var string  */
    private $mixedPath;
    /** @var Reader  */
    private $mixed;
    /** @var Reader  */
    private $single;
    /** @var Reader  */
    private $empty;
    /** @var Reader  */
    private $multi_errors;

    public function setUp(): void
    {
        $this->mixedPath    = FIXTURES . DS . 'results' . DS . 'mixed-results.xml';
        $this->mixed        = new Reader($this->mixedPath);
        $single             = FIXTURES . DS . 'results' . DS . 'single-wfailure.xml';
        $this->single       = new Reader($single);
        $empty              = FIXTURES . DS . 'results' . DS . 'empty-test-suite.xml';
        $this->empty        = new Reader($empty);
        $multi_errors       = FIXTURES . DS . 'results' . DS . 'multiple-errors-with-system-out.xml';
        $this->multi_errors = new Reader($multi_errors);
    }

    public function testInvalidPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $reader = new Reader('/path/to/nowhere');
    }

    public function testFileCannotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $reader = new Reader(FIXTURES . DS . 'results' . DS . 'empty.xml');
    }

    public function testIsSingleSuiteReturnsTrueForSingleSuite(): void
    {
        static::assertTrue($this->single->isSingleSuite());
    }

    public function testIsSingleSuiteReturnsFalseForMultipleSuites(): void
    {
        static::assertFalse($this->mixed->isSingleSuite());
    }

    public function testMixedSuiteShouldConstructRootSuite(): TestSuite
    {
        $suites = $this->mixed->getSuites();
        static::assertCount(1, $suites);
        static::assertEquals('test/fixtures/tests/', $suites[0]->name);
        static::assertEquals('7', $suites[0]->tests);
        static::assertEquals('6', $suites[0]->assertions);
        static::assertEquals('2', $suites[0]->failures);
        static::assertEquals('1', $suites[0]->errors);
        static::assertEquals('0.007625', $suites[0]->time);

        return $suites[0];
    }

    /**
     * @depends testMixedSuiteShouldConstructRootSuite
     */
    public function testMixedSuiteConstructsChildSuites(TestSuite $suite): TestSuite
    {
        static::assertCount(3, $suite->suites);
        $first = $suite->suites[0];
        static::assertEquals('UnitTestWithClassAnnotationTest', $first->name);
        static::assertEquals(
            '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php',
            $first->file
        );
        static::assertEquals('3', $first->tests);
        static::assertEquals('3', $first->assertions);
        static::assertEquals('1', $first->failures);
        static::assertEquals('0', $first->errors);
        static::assertEquals('0.006109', $first->time);

        return $first;
    }

    /**
     * @depends testMixedSuiteConstructsChildSuites
     */
    public function testMixedSuiteConstructsTestCases(TestSuite $suite): void
    {
        static::assertCount(3, $suite->cases);
        $first = $suite->cases[0];
        static::assertEquals('testTruth', $first->name);
        static::assertEquals('UnitTestWithClassAnnotationTest', $first->class);
        static::assertEquals(
            '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php',
            $first->file
        );
        static::assertEquals('10', $first->line);
        static::assertEquals('1', $first->assertions);
        static::assertEquals('0.001760', $first->time);
    }

    public function testMixedSuiteCasesLoadFailures(): void
    {
        $suites = $this->mixed->getSuites();
        $case   = $suites[0]->suites[0]->cases[1];
        static::assertCount(1, $case->failures);
        $failure = $case->failures[0];
        static::assertEquals(ExpectationFailedException::class, $failure['type']);
        static::assertEquals(
            "UnitTestWithClassAnnotationTest::testFalsehood\nFailed asserting that true is false.\n\n" .
            '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php:20',
            $failure['text']
        );
    }

    public function testMixedSuiteCasesLoadErrors(): void
    {
        $suites = $this->mixed->getSuites();
        $case   = $suites[0]->suites[1]->cases[0];
        static::assertCount(1, $case->errors);
        $error = $case->errors[0];
        static::assertEquals('Exception', $error['type']);
        static::assertEquals(
            "UnitTestWithErrorTest::testTruth\nException: Error!!!\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12',
            $error['text']
        );
    }

    public function testSingleSuiteShouldConstructRootSuite(): TestSuite
    {
        $suites = $this->single->getSuites();
        static::assertCount(1, $suites);
        static::assertEquals('UnitTestWithMethodAnnotationsTest', $suites[0]->name);
        static::assertEquals(
            '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php',
            $suites[0]->file
        );
        static::assertEquals('3', $suites[0]->tests);
        static::assertEquals('3', $suites[0]->assertions);
        static::assertEquals('1', $suites[0]->failures);
        static::assertEquals('0', $suites[0]->errors);
        static::assertEquals('0.005895', $suites[0]->time);

        return $suites[0];
    }

    /**
     * @param mixed $suite
     *
     * @depends testSingleSuiteShouldConstructRootSuite
     */
    public function testSingleSuiteShouldHaveNoChildSuites($suite): void
    {
        static::assertCount(0, $suite->suites);
    }

    /**
     * @param mixed $suite
     *
     * @depends testSingleSuiteShouldConstructRootSuite
     */
    public function testSingleSuiteConstructsTestCases($suite): void
    {
        static::assertCount(3, $suite->cases);
        $first = $suite->cases[0];
        static::assertEquals('testTruth', $first->name);
        static::assertEquals('UnitTestWithMethodAnnotationsTest', $first->class);
        static::assertEquals(
            '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php',
            $first->file
        );
        static::assertEquals('7', $first->line);
        static::assertEquals('1', $first->assertions);
        static::assertEquals('0.001632', $first->time);
    }

    public function testSingleSuiteCasesLoadFailures(): void
    {
        $suites = $this->single->getSuites();
        $case   = $suites[0]->cases[1];
        static::assertCount(1, $case->failures);
        $failure = $case->failures[0];
        static::assertEquals(ExpectationFailedException::class, $failure['type']);
        static::assertEquals(
            "UnitTestWithMethodAnnotationsTest::testFalsehood\nFailed asserting that true is false.\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php:18',
            $failure['text']
        );
    }

    public function testEmptySuiteConstructsTestCase(): void
    {
        $suites = $this->empty->getSuites();
        static::assertCount(1, $suites);

        $suite = $suites[0];
        static::assertEquals('', $suite->name);
        static::assertEquals('', $suite->file);
        static::assertEquals(0, $suite->tests);
        static::assertEquals(0, $suite->assertions);
        static::assertEquals(0, $suite->failures);
        static::assertEquals(0, $suite->errors);
        static::assertEquals(0, $suite->time);
    }

    public function testMixedGetTotals(): void
    {
        static::assertEquals(7, $this->mixed->getTotalTests());
        static::assertEquals(6, $this->mixed->getTotalAssertions());
        static::assertEquals(2, $this->mixed->getTotalFailures());
        static::assertEquals(1, $this->mixed->getTotalErrors());
        static::assertEquals(0.007625, $this->mixed->getTotalTime());
    }

    public function testSingleGetTotals(): void
    {
        static::assertEquals(3, $this->single->getTotalTests());
        static::assertEquals(3, $this->single->getTotalAssertions());
        static::assertEquals(1, $this->single->getTotalFailures());
        static::assertEquals(0, $this->single->getTotalErrors());
        static::assertEquals(0.005895, $this->single->getTotalTime());
    }

    public function testMixedGetFailureMessages(): void
    {
        $failures = $this->mixed->getFailures();
        static::assertCount(2, $failures);
        static::assertEquals(
            "UnitTestWithClassAnnotationTest::testFalsehood\nFailed asserting that true is false.\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php:20',
            $failures[0]
        );
        static::assertEquals(
            "UnitTestWithMethodAnnotationsTest::testFalsehood\nFailed asserting that true is false." .
                "\n\n/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest." .
                'php:18',
            $failures[1]
        );
    }

    public function testMixedGetErrorMessages(): void
    {
        $errors = $this->mixed->getErrors();
        static::assertCount(1, $errors);
        static::assertEquals(
            "UnitTestWithErrorTest::testTruth\nException: Error!!!\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithErrorTest.php:12',
            $errors[0]
        );
    }

    public function testSingleGetMessages(): void
    {
        $failures = $this->single->getFailures();
        static::assertCount(1, $failures);
        static::assertEquals(
            "UnitTestWithMethodAnnotationsTest::testFalsehood\nFailed asserting that true is false.\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php:18',
            $failures[0]
        );
    }

    /**
     * https://github.com/paratestphp/paratest/issues/352
     */
    public function testGetMultiErrorsMessages(): void
    {
        $errors = $this->multi_errors->getErrors();
        static::assertCount(2, $errors);
        static::assertEquals(
            "Risky Test\n" .
            "/project/vendor/phpunit/phpunit/src/TextUI/Command.php:200\n" .
            "/project/vendor/phpunit/phpunit/src/TextUI/Command.php:159\n" .
            'Custom error log on result test with multiple errors!',
            $errors[0]
        );
        static::assertEquals(
            "Risky Test\n" .
            "/project/vendor/phpunit/phpunit/src/TextUI/Command.php:200\n" .
            "/project/vendor/phpunit/phpunit/src/TextUI/Command.php:159\n" .
            'Custom error log on result test with multiple errors!',
            $errors[1]
        );
    }

    public function testMixedGetFeedback(): void
    {
        $feedback = $this->mixed->getFeedback();
        static::assertEquals(['.', 'F', '.', 'E', '.', 'F', '.'], $feedback);
    }

    public function testRemoveLog(): void
    {
        $contents = file_get_contents($this->mixedPath);
        $tmp      = FIXTURES . DS . 'results' . DS . 'dummy.xml';
        file_put_contents($tmp, $contents);
        $reader = new Reader($tmp);
        $reader->removeLog();
        static::assertFileDoesNotExist($tmp);
    }

    /**
     * Extraction of log from xml file to use in test of validation "SystemOut" result.
     *
     * @return stdClass $log
     */
    public static function extractLog(): stdClass
    {
        $log          = new stdClass();
        $result       = FIXTURES . DS . 'results' . DS . 'mixed-results-with-system-out.xml';
        $node         = new Reader($result);
        $log->failure = $node->getSuites()[0]->suites[0]->cases[1]->failures[0]['text'];
        $log->error   = $node->getSuites()[0]->suites[1]->cases[0]->errors[0]['text'];

        return $log;
    }

    public function testResultWithSystemOut(): void
    {
        $customLog   = "\nCustom error log on result test with ";
        $result      = FIXTURES . DS . 'results' . DS . 'mixed-results-with-system-out.xml';
        $failLog     = self::extractLog()->failure . $customLog . 'failure!';
        $errorLog    = self::extractLog()->error . $customLog . 'error!';
        $node        = new Reader($result);
        $resultFail  = $node->getSuites()[0]->suites[2]->cases[1]->failures[0]['text'];
        $resultError = $node->getSuites()[0]->suites[1]->cases[1]->errors[0]['text'];

        static::assertEquals($failLog, $resultFail);
        static::assertEquals($errorLog, $resultError);
    }
}
