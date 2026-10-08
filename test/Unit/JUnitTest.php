<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit;

use ParaTest\JUnit\LogMerger;
use ParaTest\JUnit\TestCase as JunitTestCase;
use ParaTest\JUnit\TestCaseWithMessage;
use ParaTest\JUnit\TestSuite;
use ParaTest\JUnit\Writer;
use ParaTest\Tests\TmpDirCreator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

use function file_get_contents;
use function file_put_contents;
use function glob;
use function str_replace;

/** @internal */
#[CoversClass(LogMerger::class)]
#[CoversClass(JunitTestCase::class)]
#[CoversClass(TestCaseWithMessage::class)]
#[CoversClass(TestSuite::class)]
#[CoversClass(Writer::class)]
final class JUnitTest extends TestCase
{
    public function testMergeJunitLogs(): void
    {
        $tmpDir = (new TmpDirCreator())->create();

        $junitFiles = [];
        $glob       = glob(FIXTURES . '/common_results/junit/*');
        self::assertNotFalse($glob);
        foreach ($glob as $file) {
            $junitFiles[] = new SplFileInfo($file);
        }

        self::assertNotSame([], $junitFiles);
        $testSuite = (new LogMerger())->merge($junitFiles);
        self::assertNotNull($testSuite);

        $outputFile = $tmpDir . '/result.xml';
        (new Writer())->write(
            $testSuite,
            $outputFile,
        );

        $xml = file_get_contents($outputFile);
        self::assertNotFalse($xml);
        $xml = str_replace('time="8.641969"', 'time="1.234567"', $xml);
        file_put_contents($outputFile, $xml);

        self::assertXmlFileEqualsXmlFile(FIXTURES . '/common_results/junit-combined.xml', $outputFile);
    }

    public function testHandleSpecialChars(): void
    {
        $tmpDir = (new TmpDirCreator())->create();

        $junitLog  = FIXTURES . '/special_chars/junit-data-provider-with-special-chars.xml';
        $testSuite = (new LogMerger())->merge([new SplFileInfo($junitLog)]);
        self::assertNotNull($testSuite);

        $outputFile = $tmpDir . '/result.xml';
        (new Writer())->write(
            $testSuite,
            $outputFile,
        );

        self::assertXmlFileEqualsXmlFile($junitLog, $outputFile);
    }

    public function testLoadTestSuiteContainingMultipleSuitesWithSameName(): void
    {
        $junitProcessLog = FIXTURES . '/github/GH997/worker_tmp_junit.xml';
        $testSuite       = TestSuite::fromFile(new SplFileInfo($junitProcessLog));

        self::assertCount(1, $testSuite->suites);
        self::assertArrayHasKey('ParaTest\Tests\fixtures\github\GH997\SuccessfulTests', $testSuite->suites);
        self::assertCount(2, $testSuite->suites['ParaTest\Tests\fixtures\github\GH997\SuccessfulTests']->cases);
        self::assertEquals(2, $testSuite->suites['ParaTest\Tests\fixtures\github\GH997\SuccessfulTests']->tests);
    }

    public function testMergeSameSuiteAcrossWorkers(): void
    {
        $junitFiles = [
            new SplFileInfo(FIXTURES . '/functional_merge/worker1.xml'),
            new SplFileInfo(FIXTURES . '/functional_merge/worker2.xml'),
        ];

        $testSuite = (new LogMerger())->merge($junitFiles);
        self::assertNotNull($testSuite);

        // 3 distinct class suites: ExampleTest (in both), OtherTest, AnotherTest
        self::assertCount(3, $testSuite->suites);
        self::assertArrayHasKey('App\Tests\ExampleTest', $testSuite->suites);
        self::assertArrayHasKey('App\Tests\OtherTest', $testSuite->suites);
        self::assertArrayHasKey('App\Tests\AnotherTest', $testSuite->suites);

        // ExampleTest appeared in both workers — all 4 cases must be present
        $exampleSuite = $testSuite->suites['App\Tests\ExampleTest'];
        self::assertSame(4, $exampleSuite->tests);
        self::assertSame(4, $exampleSuite->assertions);
        self::assertCount(4, $exampleSuite->cases);
        self::assertSame('testOne', $exampleSuite->cases[0]->name);
        self::assertSame('testTwo', $exampleSuite->cases[1]->name);
        self::assertSame('testThree', $exampleSuite->cases[2]->name);
        self::assertSame('testFour', $exampleSuite->cases[3]->name);
    }
}
