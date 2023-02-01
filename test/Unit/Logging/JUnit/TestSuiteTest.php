<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Logging\JUnit;

use InvalidArgumentException;
use ParaTest\Logging\JUnit\MessageType;
use ParaTest\Logging\JUnit\TestCaseWithMessage;
use ParaTest\Logging\JUnit\TestSuite;
use ParaTest\Tests\TestBase;
use ParaTest\Tests\TmpDirCreator;
use PHPUnit\Framework\ExpectationFailedException;

use PHPUnit\Framework\TestCase;
use function file_get_contents;
use function file_put_contents;

/**
 * @internal
 *
 * @covers \ParaTest\Logging\JUnit\TestCase
 * @covers \ParaTest\Logging\JUnit\TestCaseWithMessage
 * @covers \ParaTest\Logging\JUnit\TestSuite
 */
final class TestSuiteTest extends TestCase
{
    public function testInvalidPathThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestSuite::fromFile(new \SplFileInfo('/path/to/nowhere'));
    }

    public function testFileCannotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestSuite::fromFile(new \SplFileInfo(FIXTURES . DS . 'results' . DS . 'empty.xml'));
    }

    public function testMixedSuiteShouldConstructRootSuite(): void
    {
        $suite = TestSuite::fromFile(new \SplFileInfo(FIXTURES . DS . 'results' . DS . 'mixed-results.xml'));

        static::assertSame('./test/fixtures/failing_tests', $suite->name);
        static::assertSame(19, $suite->tests);
        static::assertSame(10, $suite->assertions);
        static::assertSame(3, $suite->failures);
        static::assertSame(1, $suite->errors);
        static::assertSame(2, $suite->warnings);
        static::assertSame(2, $suite->risky);
        static::assertSame(4, $suite->skipped);
        static::assertSame(1.234567, $suite->time);
        static::assertCount(3, $suite->suites);
        static::assertCount(0, $suite->cases);

        static::assertArrayHasKey('ParaTest\Tests\fixtures\failing_tests\UnitTestWithClassAnnotationTest', $suite->suites);
        $first = $suite->suites['ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithClassAnnotationTest'];
        static::assertSame(
            './test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php',
            $first->file,
        );
        static::assertSame(4, $first->tests);
        static::assertSame(4, $first->assertions);
        static::assertSame(1, $first->failures);
        static::assertSame(0, $first->errors);
        static::assertSame(1.234567, $first->time);

        static::assertCount(4, $first->cases);
        $firstCase = $first->cases[0];
        static::assertSame('testTruth', $firstCase->name);
        static::assertSame('ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithClassAnnotationTest', $firstCase->class);
        static::assertSame(
            './test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php',
            $firstCase->file,
        );
        static::assertSame(21, $firstCase->line);
        static::assertSame(1, $firstCase->assertions);
        static::assertSame(1.234567, $firstCase->time);
    }

    public function testMixedSuiteCasesLoadFailures(): void
    {
        $suite = TestSuite::fromFile(new \SplFileInfo(FIXTURES . DS . 'results' . DS . 'mixed-results.xml'));

        $failure = $suite->suites['ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithClassAnnotationTest']->cases[1];
        static::assertInstanceOf(TestCaseWithMessage::class, $failure);
        static::assertSame(MessageType::failure, $failure->xmlTagName);
        static::assertSame(ExpectationFailedException::class, $failure->type);
        static::assertSame(
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithClassAnnotationTest::testFalsehood\n"
            . "Failed asserting that true is false.\n"
            . "\n"
            . './test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php:32',
            $failure->text,
        );
    }

    public function testMixedSuiteCasesLoadErrors(): void
    {
        $suite = TestSuite::fromFile(new \SplFileInfo(FIXTURES . DS . 'results' . DS . 'mixed-results.xml'));

        $error = $suite->suites['ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest']->cases[0];
        static::assertInstanceOf(TestCaseWithMessage::class, $error);
        static::assertSame(MessageType::error, $error->xmlTagName);
        static::assertSame('RuntimeException', $error->type);
        static::assertSame(
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest::testTruth\n"
            . "RuntimeException: Error!!!\n"
            . "\n"
            . './test/fixtures/failing_tests/UnitTestWithErrorTest.php:19',
            $error->text,
        );
    }

    public function testSingleSuiteShouldConstructRootSuite(): void
    {
        $suite = TestSuite::fromFile(new \SplFileInfo(FIXTURES . DS . 'results' . DS . 'mixed-results.xml'));

        static::assertSame('ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest', $suite->name);
        static::assertSame(
            './test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php',
            $suite->file,
        );
        static::assertSame(3, $suite->tests);
        static::assertSame(3, $suite->assertions);
        static::assertSame(1, $suite->failures);
        static::assertSame(0, $suite->errors);
        static::assertSame(1.234567, $suite->time);
        
        static::assertCount(0, $suite->suites);
        static::assertCount(3, $suite->cases);
        
        $first = $suite->cases[0];
        static::assertSame('testTruth', $first->name);
        static::assertSame('ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest', $first->class);
        static::assertSame(
            './test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php',
            $first->file,
        );
        static::assertSame(13, $first->line);
        static::assertSame(1, $first->assertions);
        static::assertSame(1.234567, $first->time);
    }

    public function testSingleSuiteCasesLoadFailures(): void
    {
        $suite = TestSuite::fromFile(new \SplFileInfo(FIXTURES . DS . 'results' . DS . 'single-wfailure.xml'));

        $failure = $suite->cases[1];
        static::assertInstanceOf(TestCaseWithMessage::class, $failure);
        static::assertSame(MessageType::failure, $failure->xmlTagName);
        static::assertSame(ExpectationFailedException::class, $failure->type);
        static::assertSame(
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest::testFalsehood\n"
            . "Failed asserting that two strings are identical.\n"
            . "--- Expected\n"
            . "+++ Actual\n"
            . "@@ @@\n"
            . "-'foo'\n"
            . "+'bar'\n"
            . "\n"
            . './test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:21',
            $failure->text,
        );
    }

    public function testEmptySuiteConstructsTestCase(): void
    {
        $suite = TestSuite::fromFile(new \SplFileInfo(FIXTURES . DS . 'results' . DS . 'empty-test-suite.xml'));

        static::assertSame('', $suite->name);
        static::assertSame('', $suite->file);
        static::assertSame(0, $suite->tests);
        static::assertSame(0, $suite->assertions);
        static::assertSame(0, $suite->failures);
        static::assertSame(0, $suite->errors);
        static::assertSame(0.0, $suite->time);
    }
}
