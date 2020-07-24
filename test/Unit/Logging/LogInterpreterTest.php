<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Logging;

use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\LogInterpreter;
use ParaTest\Tests\Unit\ResultTester;

use function array_pop;

class LogInterpreterTest extends ResultTester
{
    protected $interpreter;

    public function setUp(): void
    {
        parent::setUp();
        $this->interpreter = new LogInterpreter();
        $this->interpreter
            ->addReader($this->getReader('mixedSuite'))
            ->addReader($this->getReader('passingSuite'));
    }

    public function testConstructor(): void
    {
        $interpreter = new LogInterpreter();
        $this->assertEquals([], $this->getObjectValue($interpreter, 'readers'));
    }

    public function testAddReaderIncrementsReaders(): void
    {
        $reader = $this->getMockReader();
        $this->interpreter->addReader($reader);
        $this->assertCount(3, $this->getObjectValue($this->interpreter, 'readers'));
    }

    public function testAddReaderReturnsSelf(): void
    {
        $reader = $this->getMockReader();
        $self   = $this->interpreter->addReader($reader);
        $this->assertSame($self, $this->interpreter);
    }

    public function testGetReaders(): void
    {
        $reader = $this->getMockReader();
        $this->interpreter->addReader($reader);
        $readers = $this->interpreter->getReaders();
        $this->assertCount(3, $readers);
        $last = array_pop($readers);
        $this->assertSame($reader, $last);
    }

    public function testGetTotalTests(): void
    {
        $this->assertEquals(10, $this->interpreter->getTotalTests());
    }

    public function testGetTotalAssertions(): void
    {
        $this->assertEquals(9, $this->interpreter->getTotalAssertions());
    }

    public function testGetTotalFailures(): void
    {
        $this->assertEquals(2, $this->interpreter->getTotalFailures());
    }

    public function testGetTotalErrors(): void
    {
        $this->assertEquals(1, $this->interpreter->getTotalErrors());
    }

    public function testIsSuccessfulReturnsFalseIfFailuresPresentAndNoErrors(): void
    {
        $interpreter = new LogInterpreter();
        $interpreter->addReader($this->getReader('failureSuite'));
        $this->assertFalse($interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseIfErrorsPresentAndNoFailures(): void
    {
        $interpreter = new LogInterpreter();
        $interpreter->addReader($this->getReader('errorSuite'));
        $this->assertFalse($interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseIfErrorsAndFailuresPresent(): void
    {
        $this->assertFalse($this->interpreter->isSuccessful());
    }

    public function testIsSuccessfulReturnsTrueIfNoErrorsOrFailures(): void
    {
        $interpreter = new LogInterpreter();
        $interpreter->addReader($this->getReader('passingSuite'));
        $this->assertTrue($interpreter->isSuccessful());
    }

    public function testGetErrorsReturnsArrayOfErrorMessages(): void
    {
        $errors = [
            "UnitTestWithErrorTest::testTruth\nException: Error!!!\n\n/home/brian/Projects/parallel-phpunit/" .
            'test/fixtures/tests/UnitTestWithErrorTest.php:12',
        ];
        $this->assertEquals($errors, $this->interpreter->getErrors());
    }

    public function testGetFailuresReturnsArrayOfFailureMessages(): void
    {
        $failures = [
            "UnitTestWithClassAnnotationTest::testFalsehood\nFailed asserting that true is false.\n\n/" .
                'home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php:20',
            "UnitTestWithMethodAnnotationsTest::testFalsehood\nFailed asserting that true is false.\n\n" .
                '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithMethodAnnotationsTest.php:18',
        ];

        $this->assertEquals($failures, $this->interpreter->getFailures());
    }

    public function testGetCasesReturnsAllCases(): void
    {
        $cases = $this->interpreter->getCases();
        $this->assertCount(10, $cases);
    }

    public function testGetCasesExtendEmptyCasesFromSuites(): void
    {
        $interpreter        = new LogInterpreter();
        $dataProviderReader = $this->getReader('dataProviderSuite');
        $interpreter->addReader($dataProviderReader);
        $cases = $interpreter->getCases();
        $this->assertCount(10, $cases);
        foreach ($cases as $name => $case) {
            $this->assertNotNull($case->class);
            $this->assertNotNull($case->file);
            if ($case->name === 'testNumericDataProvider5 with data set #3') {
                $this->assertEquals($case->class, 'DataProviderTest1');
            } elseif ($case->name === 'testNamedDataProvider5 with data set #3') {
                $this->assertEquals($case->class, 'DataProviderTest2');
            } else {
                $this->assertEquals($case->class, 'DataProviderTest');
            }

            if ($case->name === 'testNumericDataProvider5 with data set #4') {
                $this->assertEquals(
                    $case->file,
                    '/var/www/project/vendor/brianium/paratest/test/fixtures/dataprovider-tests/DataProviderTest1.php'
                );
            } elseif ($case->name === 'testNamedDataProvider5 with data set #4') {
                $this->assertEquals(
                    $case->file,
                    '/var/www/project/vendor/brianium/paratest/test/fixtures/dataprovider-tests/DataProviderTest2.php'
                );
            } else {
                $this->assertEquals(
                    $case->file,
                    '/var/www/project/vendor/brianium/paratest/test/fixtures/dataprovider-tests/DataProviderTest.php'
                );
            }
        }
    }

    public function testFlattenCasesReturnsCorrectNumberOfSuites()
    {
        $suites = $this->interpreter->flattenCases();
        $this->assertCount(4, $suites);

        return $suites;
    }

    /**
     * @param mixed $suites
     *
     * @depends testFlattenCasesReturnsCorrectNumberOfSuites
     */
    public function testFlattenedSuiteHasCorrectTotals($suites): void
    {
        $first = $suites[0];
        $this->assertEquals('UnitTestWithClassAnnotationTest', $first->name);
        $this->assertEquals(
            '/home/brian/Projects/parallel-phpunit/test/fixtures/tests/UnitTestWithClassAnnotationTest.php',
            $first->file
        );
        $this->assertEquals('3', $first->tests);
        $this->assertEquals('3', $first->assertions);
        $this->assertEquals('1', $first->failures);
        $this->assertEquals('0', $first->errors);
        $this->assertEquals('0.006109', $first->time);
    }

    protected function getReader($suiteName)
    {
        return new Reader($this->$suiteName->getTempFile());
    }

    protected function getMockReader()
    {
        return $this->getMockBuilder(Reader::class)
                    ->disableOriginalConstructor()
                    ->getMock();
    }
}
