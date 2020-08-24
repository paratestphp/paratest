<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Coverage;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Tests\TestBase;
use RuntimeException;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\Report\PHP;

use function touch;

/**
 * @internal
 *
 * @covers \ParaTest\Coverage\CoverageMerger
 */
final class CoverageMergerTest extends TestBase
{
    protected function setUpTest(): void
    {
        static::skipIfCodeCoverageNotEnabled();
    }

    /**
     * @dataProvider provideTestLimit
     */
    public function testMerge(int $testLimit): void
    {
        $firstFile  = PARATEST_ROOT . DS . 'src' . DS . 'Logging' . DS . 'LogInterpreter.php';
        $secondFile = PARATEST_ROOT . DS . 'src' . DS . 'Logging' . DS . 'MetaProviderInterface.php';

        // Every time the two above files are changed, the line numbers
        // may change, and so these two numbers may need adjustments
        $firstFileFirstLine  = 47;
        $secondFileFirstLine = 55;

        $filter = new Filter();
        $filter->includeFiles([$firstFile, $secondFile]);

        $data      = RawCodeCoverageData::fromXdebugWithoutPathCoverage([
            $firstFile => [$firstFileFirstLine => 1],
            $secondFile => [$secondFileFirstLine => 1],
        ]);
        $coverage1 = new CodeCoverage(Driver::forLineCoverage($filter), $filter);
        $coverage1->append(
            $data,
            'Test1'
        );

        $data      = RawCodeCoverageData::fromXdebugWithoutPathCoverage([
            $firstFile => [$firstFileFirstLine => 1, 1 + $firstFileFirstLine => 1],
        ]);
        $coverage2 = new CodeCoverage(Driver::forLineCoverage($filter), $filter);
        $coverage2->append(
            $data,
            'Test2'
        );

        $target1   = TMP_DIR . DS . 'coverage1.php';
        $target2   = TMP_DIR . DS . 'coverage2.php';
        $phpReport = new PHP();
        $phpReport->process($coverage1, $target1);
        $phpReport->process($coverage2, $target2);

        $merger = new CoverageMerger($testLimit);
        $merger->addCoverageFromFile($target1);
        $merger->addCoverageFromFile($target2);

        static::assertFileDoesNotExist($target1);
        static::assertFileDoesNotExist($target2);

        $coverage = $merger->getCodeCoverageObject();
        static::assertNotNull($coverage);
        $data = $coverage->getData()->lineCoverage();

        if ($testLimit === 0) {
            static::assertCount(2, $data[$firstFile][$firstFileFirstLine]);
            static::assertEquals('Test1', $data[$firstFile][$firstFileFirstLine][0]);
            static::assertEquals('Test2', $data[$firstFile][$firstFileFirstLine][1]);
        } else {
            static::assertCount(1, $data[$firstFile][$firstFileFirstLine]);
            static::assertEquals('Test1', $data[$firstFile][$firstFileFirstLine][0]);
        }

        static::assertCount(1, $data[$secondFile][$secondFileFirstLine]);
        static::assertEquals('Test1', $data[$secondFile][$secondFileFirstLine][0]);
    }

    /**
     * @return array<string, int[]>
     */
    public function provideTestLimit(): array
    {
        return [
            'unlimited' => [0],
            'limited' => [1],
        ];
    }

    public function testCoverageFileIsEmpty(): void
    {
        $filename = TMP_DIR . DS . 'coverage.php';
        touch($filename);
        $coverageMerger = new CoverageMerger(0);

        $this->expectException(RuntimeException::class);
        $regex = '/Coverage file .*? is empty. This means a PHPUnit process has crashed./';
        $this->expectExceptionMessageMatches($regex);

        $coverageMerger->addCoverageFromFile($filename);
    }
}
