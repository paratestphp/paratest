<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Coverage;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Tests\TestBase;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\RawCodeCoverageData;

use function assert;

class CoverageMergerTest extends TestBase
{
    protected function setUp(): void
    {
        $this->skipIfCodeCoverageNotEnabled();
    }

    /**
     * Test merge for code coverage library 4 version.
     *
     * @requires function \SebastianBergmann\CodeCoverage\CodeCoverage::merge
     */
    public function testSimpleMerge(): void
    {
        $firstFile  = PARATEST_ROOT . DS . 'src' . DS . 'Logging' . DS . 'LogInterpreter.php';
        $secondFile = PARATEST_ROOT . DS . 'src' . DS . 'Logging' . DS . 'MetaProvider.php';

        // Every time the two above files are changed, the line numbers
        // may change, and so these two numbers may need adjustments
        $firstFileFirstLine  = 46;
        $secondFileFirstLine = 53;

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

        $merger = new CoverageMerger();
        $this->call($merger, 'addCoverage', $coverage1);
        $this->call($merger, 'addCoverage', $coverage2);

        $coverage = $this->getObjectValue($merger, 'coverage');
        assert($coverage instanceof CodeCoverage);

        $this->assertInstanceOf(CodeCoverage::class, $coverage);

        $data = $coverage->getData()->lineCoverage();

        $this->assertCount(2, $data[$firstFile][$firstFileFirstLine]);
        $this->assertEquals('Test1', $data[$firstFile][$firstFileFirstLine][0]);
        $this->assertEquals('Test2', $data[$firstFile][$firstFileFirstLine][1]);

        $this->assertCount(1, $data[$secondFile][$secondFileFirstLine]);
        $this->assertEquals('Test1', $data[$secondFile][$secondFileFirstLine][0]);
    }

    /**
     * Test merge with limits
     *
     * @requires function \SebastianBergmann\CodeCoverage\CodeCoverage::merge
     */
    public function testSimpleMergeLimited(): void
    {
        $firstFile  = PARATEST_ROOT . DS . 'src' . DS . 'Logging' . DS . 'LogInterpreter.php';
        $secondFile = PARATEST_ROOT . DS . 'src' . DS . 'Logging' . DS . 'MetaProvider.php';

        // Every time the two above files are changed, the line numbers
        // may change, and so these two numbers may need adjustments
        $firstFileFirstLine  = 46;
        $secondFileFirstLine = 53;

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

        $merger = new CoverageMerger($test_limit = 1);
        $this->call($merger, 'addCoverage', $coverage1);
        $this->call($merger, 'addCoverage', $coverage2);

        $coverage = $this->getObjectValue($merger, 'coverage');
        assert($coverage instanceof CodeCoverage);

        $this->assertInstanceOf(CodeCoverage::class, $coverage);
        $data = $coverage->getData()->lineCoverage();

        $this->assertCount(1, $data[$firstFile][$firstFileFirstLine]);
        $this->assertCount(1, $data[$secondFile][$secondFileFirstLine]);
    }
}
