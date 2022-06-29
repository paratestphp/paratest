<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Logging;

use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\TestSuite;
use ParaTest\Logging\LogInterpreter;
use ParaTest\Tests\Unit\ResultTester;

use function array_pop;

/**
 * @internal
 *
 * @covers \ParaTest\Logging\LogInterpreter
 */
final class LogInterpreterTest extends ResultTester
{
    /** @var LogInterpreter */
    private $interpreter;

    protected function setUpInterpreter(): void
    {
        $this->interpreter = new LogInterpreter();
        $this->interpreter->addReader(new Reader($this->mixedSuite->getTempFile()));
        $this->interpreter->addReader(new Reader($this->passingSuite->getTempFile()));
    }

    public function testConstructor(): void
    {
        $interpreter = new LogInterpreter();
        static::assertSame([], $this->getObjectValue($interpreter, 'readers'));
    }

    public function testAddReaderIncrementsReaders(): void
    {
        static::assertCount(2, $this->getObjectValue($this->interpreter, 'readers'));
        $this->interpreter->addReader(new Reader($this->failureSuite->getTempFile()));
        static::assertCount(3, $this->getObjectValue($this->interpreter, 'readers'));
    }

    public function testGetReaders(): void
    {
        $reader = new Reader($this->failureSuite->getTempFile());
        $this->interpreter->addReader($reader);
        $readers = $this->interpreter->getReaders();
        static::assertCount(3, $readers);
        $last = array_pop($readers);
        static::assertSame($reader, $last);
    }

    public function testGetTotals(): void
    {
        static::assertSame(22, $this->interpreter->getTotalTests());
        static::assertSame(13, $this->interpreter->getTotalAssertions());
        static::assertSame(3, $this->interpreter->getTotalErrors());
        static::assertSame(3, $this->interpreter->getTotalFailures());
        static::assertSame(2, $this->interpreter->getTotalWarnings());
        static::assertSame(4, $this->interpreter->getTotalSkipped());
        static::assertSame(2.469134, $this->interpreter->getTotalTime());
    }

    public function testIsSuccessfulReturnsFalseIfFailuresPresentAndNoErrors(): void
    {
        $interpreter = new LogInterpreter();
        $interpreter->addReader(new Reader($this->failureSuite->getTempFile()));
        static::assertFalse($interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseIfErrorsPresentAndNoFailures(): void
    {
        $interpreter = new LogInterpreter();
        $interpreter->addReader(new Reader($this->errorSuite->getTempFile()));
        static::assertFalse($interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseIfErrorsAndFailuresPresent(): void
    {
        static::assertFalse($this->interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsTrueIfNoErrorsOrFailures(): void
    {
        $interpreter = new LogInterpreter();
        $interpreter->addReader(new Reader($this->passingSuite->getTempFile()));
        static::assertTrue($interpreter->isSuccessful());
    }

    public function testGetErrorsReturnsArrayOfErrorMessages(): void
    {
        $errors = [
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest::testTruth\n"
            ."RuntimeException: Error!!!\n"
            ."\n"
            .'./test/fixtures/failing_tests/UnitTestWithErrorTest.php:21',
        ];
        static::assertSame($errors, $this->interpreter->getErrors());
    }

    public function testGetWarningsReturnsArrayOfErrorMessages(): void
    {
        $errors = [
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest::testWarning\nMyWarning",
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest::testWarning\nMyWarning",
        ];
        static::assertSame($errors, $this->interpreter->getWarnings());
    }

    public function testGetFailuresReturnsArrayOfFailureMessages(): void
    {
        $failures = [
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithClassAnnotationTest::testFalsehood\n"
            ."Failed asserting that true is false.\n"
            ."\n"
            .'./test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php:32',
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithErrorTest::testFalsehood\n"
            ."Failed asserting that true is false.\n"
            ."\n"
            .'./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:29',
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest::testFalsehood\n"
            ."Failed asserting that true is false.\n"
            ."\n"
            .'./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:29',
        ];

        static::assertSame($failures, $this->interpreter->getFailures());
    }

    public function testGetRiskyReturnsArrayOfErrorMessages(): void
    {
        $errors = [
            'ParaTest\Tests\fixtures\failing_tests\UnitTestWithErrorTest::testRisky'."\n"
            .'This test did not perform any assertions'."\n"
            ."\n"
            .'./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:68',
            'ParaTest\Tests\fixtures\failing_tests\UnitTestWithMethodAnnotationsTest::testRisky'."\n"
            .'This test did not perform any assertions'."\n"
            ."\n"
            .'./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:68',
        ];
        static::assertSame($errors, $this->interpreter->getRisky());
    }

    public function testGetSkippedReturnsArrayOfTestNames(): void
    {
        $interpreter = new LogInterpreter();
        $interpreter->addReader(new Reader($this->skipped->getTempFile()));
        $skipped = [
            "ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithMethodAnnotationsTest::testSkipped\n"
            ."\n"
            .'./test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php:52',
        ];
        static::assertSame($skipped, $interpreter->getSkipped());
    }

    public function testGetCasesReturnsAllCases(): void
    {
        $testSuite = $this->interpreter->mergeReaders();
        static::assertSame(22, $testSuite->tests);
    }

    public function testFlattenedSuiteHasCorrectTotals(): void
    {
        $suite = $this->interpreter->mergeReaders();
        static::assertCount(2, $suite->suites);
        $mainFirst = $suite->suites[0];
        static::assertSame('./test/fixtures/failing_tests', $mainFirst->name);
        static::assertCount(3, $mainFirst->suites);
        $first = $mainFirst->suites[0];
        static::assertSame('ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithClassAnnotationTest', $first->name);
        static::assertSame(
            './test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php',
            $first->file
        );
        static::assertSame(4, $first->tests);
        static::assertSame(4, $first->assertions);
        static::assertSame(1, $first->failures);
        static::assertSame(0, $first->warnings);
        static::assertSame(0, $first->skipped);
        static::assertSame(0, $first->errors);
        static::assertSame(1.234567, $first->time);
    }
}
