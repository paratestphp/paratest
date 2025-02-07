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

        self::assertXmlFileEqualsXmlFile(FIXTURES . '/common_results/combined.xml', $outputFile);
    }

    public function testHandleSpecialChars(): void
    {
        $tmpDir = (new TmpDirCreator())->create();

        $junitLog  = FIXTURES . '/special_chars/data-provider-with-special-chars.xml';
        $testSuite = (new LogMerger())->merge([new SplFileInfo($junitLog)]);
        self::assertNotNull($testSuite);

        $outputFile = $tmpDir . '/result.xml';
        (new Writer())->write(
            $testSuite,
            $outputFile,
        );

        self::assertXmlFileEqualsXmlFile($junitLog, $outputFile);
    }
}
