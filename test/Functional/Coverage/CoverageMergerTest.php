<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional\Coverage;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Tests\TestBase;
use RuntimeException;
use SebastianBergmann\CodeCoverage\CodeCoverage;

use function mkdir;
use function str_replace;
use function sys_get_temp_dir;
use function uniqid;

class CoverageMergerTest extends TestBase
{
    /**
     * Directory to store coverage files.
     *
     * @var string
     */
    private $targetDir;

    protected function setUp(): void
    {
        parent::setUp();

        static::skipIfCodeCoverageNotEnabled();

        $this->targetDir = str_replace('.', '_', sys_get_temp_dir() . DS . uniqid('paratest-', true));
        $this->removeDirectory($this->targetDir);
        mkdir($this->targetDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->targetDir);

        parent::tearDown();
    }

    /**
     * @param string[] $coverageFiles
     *
     * @dataProvider getCoverageFileProvider
     */
    public function testCoverageFromFileIsDeletedAfterAdd(array $coverageFiles): void
    {
        $filename = $this->copyCoverageFile($coverageFiles[0], $this->targetDir);

        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile($filename);

        static::assertFileDoesNotExist($filename);
    }

    /**
     * @param string[] $coverageFiles
     *
     * @dataProvider getCoverageFileProvider
     */
    public function testCodeCoverageObjectIsCreatedFromCoverageFile(array $coverageFiles, string $expectedClass): void
    {
        $filename = $this->copyCoverageFile($coverageFiles[0], $this->targetDir);

        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile($filename);

        $coverage = $this->getCoverage($coverageMerger);

        static::assertInstanceOf($expectedClass, $coverage);
        static::assertArrayHasKey(
            'ParaTest\\Runners\\PHPUnit\\RunnerTest::testConstructor',
            $coverage->getTests(),
            'Code coverage was not added from file'
        );
    }

    /**
     * @param string[] $coverageFiles
     *
     * @dataProvider getCoverageFileProvider
     */
    public function testCoverageIsMergedOnSecondAddCoverageFromFile(array $coverageFiles): void
    {
        $filename1 = $this->copyCoverageFile($coverageFiles[0], $this->targetDir);
        $filename2 = $this->copyCoverageFile($coverageFiles[1], $this->targetDir);

        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile($filename1);

        $coverage = $this->getCoverage($coverageMerger);

        static::assertArrayHasKey(
            'ParaTest\\Runners\\PHPUnit\\RunnerTest::testConstructor',
            $coverage->getTests(),
            'Code coverage was not added from first file'
        );
        static::assertArrayNotHasKey(
            'ParaTest\\Runners\\PHPUnit\\ResultPrinterTest::testConstructor',
            $coverage->getTests()
        );

        $coverageMerger->addCoverageFromFile($filename2);

        static::assertArrayHasKey(
            'ParaTest\\Runners\\PHPUnit\\RunnerTest::testConstructor',
            $coverage->getTests(),
            'Code coverage from first file was removed'
        );
        static::assertArrayHasKey(
            'ParaTest\\Runners\\PHPUnit\\ResultPrinterTest::testConstructor',
            $coverage->getTests(),
            'Code coverage was not added from second file'
        );
    }

    public function testCoverageFileIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $regex = '/Coverage file .*? is empty. This means a PHPUnit process has crashed./';
        $this->expectExceptionMessageMatches($regex);
        $filename = $this->copyCoverageFile('coverage-tests' . DS . 'empty_test.cov', $this->targetDir);

        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile($filename);
    }

    public function testCoverageFileIsNull(): void
    {
        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile(null);

        $this->assertNull($coverageMerger->getCodeCoverageObject());
    }

    public function testCoverageFileDoesNotExist(): void
    {
        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile('no-such-file.cov');

        $this->assertNull($coverageMerger->getCodeCoverageObject());
    }

    /**
     * @return array
     */
    public static function getCoverageFileProvider(): array
    {
        $version       = 'CodeCoverage >4.0';
        $filenames     = [
            'coverage-tests/runner_test.cov',
            'coverage-tests/result_printer_test.cov',
        ];
        $coverageClass = CodeCoverage::class;

        return [
            $version => [
                'filenames' => $filenames,
                'expected coverage class' => $coverageClass,
            ],
        ];
    }

    private function getCoverage(CoverageMerger $coverageMerger): CodeCoverage
    {
        return $this->getObjectValue($coverageMerger, 'coverage');
    }
}
