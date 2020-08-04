<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Logging;

use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\TestSuite;
use ParaTest\Logging\LogInterpreter;
use ParaTest\Tests\Unit\ResultTester;

use function array_pop;

final class LogInterpreterTest extends ResultTester
{
    /** @var LogInterpreter */
    private $interpreter;

    protected function setUpInterpreter(): void
    {
        $this->interpreter = new LogInterpreter();
        $this->interpreter
            ->addReader(new Reader($this->mixedSuite->getTempFile()))
            ->addReader(new Reader($this->passingSuite->getTempFile()));
    }

    public function testConstructor(): void
    {
        $interpreter = new LogInterpreter();
        static::assertEquals([], $this->getObjectValue($interpreter, 'readers'));
    }

    public function testAddReaderIncrementsReaders(): void
    {
        static::assertCount(2, $this->getObjectValue($this->interpreter, 'readers'));
        $this->interpreter->addReader(new Reader($this->failureSuite->getTempFile()));
        static::assertCount(3, $this->getObjectValue($this->interpreter, 'readers'));
    }

    public function testAddReaderReturnsSelf(): void
    {
        $self = $this->interpreter->addReader(new Reader($this->failureSuite->getTempFile()));
        static::assertSame($self, $this->interpreter);
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

    public function testGetTotalTests(): void
    {
        static::assertEquals(10, $this->interpreter->getTotalTests());
    }

    public function testGetTotalAssertions(): void
    {
        static::assertEquals(9, $this->interpreter->getTotalAssertions());
    }

    public function testGetTotalFailures(): void
    {
        static::assertEquals(2, $this->interpreter->getTotalFailures());
    }

    public function testGetTotalErrors(): void
    {
        static::assertEquals(1, $this->interpreter->getTotalErrors());
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
            "UnitTestWithErrorTest::testTruth\nException: Error!!!\n\n/home/brian/Projects/parallel-phpunit/" .
            'test/fixtures/tests/UnitTestWithErrorTest.php:12',
        ];
        static::assertEquals($errors, $this->interpreter->getErrors());
    }

    public function testGetFailuresReturnsArrayOfFailureMessages(): void
    {
        $failures = [
            "UnitTestWithClassAnnotationTest::testFalsehood\nFailed asserting that true is false.\n\n/" .
                'home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php:20',
            "UnitTestWithMethodAnnotationsTest::testFalsehood\nFailed asserting that true is false.\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php:18',
        ];

        static::assertEquals($failures, $this->interpreter->getFailures());
    }

    public function testGetCasesReturnsAllCases(): void
    {
        $cases = $this->interpreter->getCases();
        static::assertCount(10, $cases);
    }

    public function testGetCasesExtendEmptyCasesFromSuites(): void
    {
        $interpreter        = new LogInterpreter();
        $dataProviderReader = new Reader($this->dataProviderSuite->getTempFile());
        $interpreter->addReader($dataProviderReader);
        $cases = $interpreter->getCases();
        static::assertCount(10, $cases);
        foreach ($cases as $name => $case) {
            if ($case->name === 'testNumericDataProvider5 with data set #3') {
                static::assertEquals($case->class, 'DataProviderTest1');
            } elseif ($case->name === 'testNamedDataProvider5 with data set #3') {
                static::assertEquals($case->class, 'DataProviderTest2');
            } else {
                static::assertEquals($case->class, 'DataProviderTest');
            }

            if ($case->name === 'testNumericDataProvider5 with data set #4') {
                static::assertEquals(
                    $case->file,
                    '/var/www/project/vendor/brianium/paratest/test/fixtures/dataprovider-tests/DataProviderTest1.php'
                );
            } elseif ($case->name === 'testNamedDataProvider5 with data set #4') {
                static::assertEquals(
                    $case->file,
                    '/var/www/project/vendor/brianium/paratest/test/fixtures/dataprovider-tests/DataProviderTest2.php'
                );
            } else {
                static::assertEquals(
                    $case->file,
                    '/var/www/project/vendor/brianium/paratest/test/fixtures/dataprovider-tests/DataProviderTest.php'
                );
            }
        }
    }

    /**
     * @return TestSuite[]
     */
    public function testFlattenCasesReturnsCorrectNumberOfSuites(): array
    {
        $suites = $this->interpreter->flattenCases();
        static::assertCount(4, $suites);

        return $suites;
    }

    /**
     * @param TestSuite[] $suites
     *
     * @depends testFlattenCasesReturnsCorrectNumberOfSuites
     */
    public function testFlattenedSuiteHasCorrectTotals(array $suites): void
    {
        $first = $suites[0];
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
    }
}
