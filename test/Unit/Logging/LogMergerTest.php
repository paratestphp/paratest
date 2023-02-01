<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Logging;

use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\LogMerger;
use ParaTest\Tests\Unit\ResultTester;

use function array_pop;

/**
 * @internal
 *
 * @covers \ParaTest\Logging\LogMerger
 */
final class LogMergerTest extends ResultTester
{
    private LogMerger $interpreter;

    protected function setUpInterpreter(): void
    {
        $this->interpreter = new LogMerger();
        $this->interpreter->addReader(new Reader($this->mixedSuite->getTempFile()));
        $this->interpreter->addReader(new Reader($this->passingSuite->getTempFile()));
    }

    public function testMergeReaders(): void
    {
        $one = new LogMerger();
        $one->addReader(new Reader($this->mixedSuite->getTempFile()));
        $one->addReader(new Reader($this->passingSuite->getTempFile()));

        $two = new LogMerger();
        $two->addReader(new Reader($this->passingSuite->getTempFile()));
        $two->addReader(new Reader($this->mixedSuite->getTempFile()));

        $oneResult = $one->mergeReaders();
        $twoResult = $two->mergeReaders();

        static::assertSame(22, $oneResult->tests);
        static::assertSame(13, $oneResult->assertions);
        static::assertSame(3, $oneResult->failures);
        static::assertSame(1, $oneResult->errors);
        static::assertSame(2, $oneResult->warnings);
        static::assertSame(2, $oneResult->risky);
        static::assertSame(4, $oneResult->skipped);
        static::assertSame(2.469134, $oneResult->time);
        static::assertCount(2, $oneResult->suites);

        static::assertSame($oneResult->tests, $twoResult->tests);
        static::assertSame($oneResult->assertions, $twoResult->assertions);
        static::assertSame($oneResult->failures, $twoResult->failures);
        static::assertSame($oneResult->errors, $twoResult->errors);
        static::assertSame($oneResult->warnings, $twoResult->warnings);
        static::assertSame($oneResult->risky, $twoResult->risky);
        static::assertSame($oneResult->skipped, $twoResult->skipped);
        static::assertSame($oneResult->time, $twoResult->time);

        static::assertEquals($oneResult->suites, $twoResult->suites);
    }

    public function testFlattenedSuiteHasCorrectTotals(): void
    {
        $suite = $this->interpreter->mergeReaders();
        static::assertCount(2, $suite->suites);
        $mainFirst = $suite->suites['./test/fixtures/failing_tests'];
        static::assertCount(3, $mainFirst->suites);
        $first = $mainFirst->suites['ParaTest\\Tests\\fixtures\\failing_tests\\UnitTestWithClassAnnotationTest'];
        static::assertSame(
            './test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php',
            $first->file,
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
