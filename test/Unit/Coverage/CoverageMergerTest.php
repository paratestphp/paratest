<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Coverage;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Tests\TestBase;
use RuntimeException;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\PHP;

use function touch;

/**
 * @internal
 *
 * @covers \ParaTest\Coverage\CoverageMerger
 */
final class CoverageMergerTest extends TestBase
{
    public function testCoverageFileIsEmpty(): void
    {
        $filename = $this->tmpDir . DS . 'coverage.php';
        touch($filename);

        $coverageMerger = new CoverageMerger(new CodeCoverage(
            new class extends Driver {
                public function nameAndVersion(): string
                {
                    return '';
                }

                public function start(): void
                {
                }

                public function stop(): RawCodeCoverageData
                {
                    return RawCodeCoverageData::fromXdebugWithoutPathCoverage([]);
                }
            },
            new Filter()
        ));

        $this->expectException(RuntimeException::class);
        $regex = '/Coverage file .*? is empty. This means a PHPUnit process has crashed./';
        $this->expectExceptionMessageMatches($regex);

        $coverageMerger->addCoverageFromFile(new \SplFileInfo($filename));
    }
}
