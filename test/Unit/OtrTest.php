<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit;

use ParaTest\Otr\LogMerger;
use ParaTest\Otr\Writer;
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
final class OtrTest extends TestCase
{
    public function testMergeOtrLogs(): void
    {
        $tmpDir = (new TmpDirCreator())->create();

        $otrFiles = [];
        $glob       = glob(FIXTURES . '/common_results/otr/*');
        self::assertNotFalse($glob);
        foreach ($glob as $file) {
            $otrFiles[] = new SplFileInfo($file);
        }

        self::assertNotSame([], $otrFiles);
        $testSuite = (new LogMerger())->merge($otrFiles);
        self::assertNotNull($testSuite);

        $outputFile = $tmpDir . '/result.xml';
        (new Writer())->write(
            $testSuite,
            $outputFile,
        );

        $xml = file_get_contents($outputFile);
        self::assertNotFalse($xml);
        file_put_contents($outputFile, $xml);

        self::assertXmlFileEqualsXmlFile(FIXTURES . '/common_results/otr-combined.xml', $outputFile);
    }
}
