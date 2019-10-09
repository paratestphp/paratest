<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional\Coverage;

use ParaTest\Coverage\CoverageMerger;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use ParaTest\Tests\TestBase;

class CoverageMergerTest extends TestBase
{
    /**
     * Directory to store coverage files.
     *
     * @var string
     */
    private $targetDir;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        static::skipIfCodeCoverageNotEnabled();

        $this->targetDir = str_replace('.', '_', uniqid('/tmp/paratest-', true));
        $this->removeDirectory($this->targetDir);
        mkdir($this->targetDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->removeDirectory($this->targetDir);

        parent::tearDown();
    }

    /**
     * @dataProvider getCoverageFileProvider
     *
     * @param string[] $coverageFiles
     */
    public function testCoverageFromFileIsDeletedAfterAdd(array $coverageFiles)
    {
        $filename = $this->copyCoverageFile($coverageFiles[0], $this->targetDir);

        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile($filename);

        static::assertFileNotExists($filename);
    }

    /**
     * @dataProvider getCoverageFileProvider
     *
     * @param string[] $coverageFiles
     * @param $expectedClass
     */
    public function testCodeCoverageObjectIsCreatedFromCoverageFile(array $coverageFiles, $expectedClass)
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
     * @dataProvider getCoverageFileProvider
     *
     * @param string[] $coverageFiles
     */
    public function testCoverageIsMergedOnSecondAddCoverageFromFile(array $coverageFiles)
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

    public function testCoverageFileIsEmpty()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageRegExp('/Coverage file .*? is empty. This means a PHPUnit process has crashed./');
        $filename = $this->copyCoverageFile('coverage-tests/empty_test.cov', $this->targetDir);

        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile($filename);
    }

    public function testCoverageFileIsNull()
    {
        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile(null);

        $this->assertNull($coverageMerger->getCodeCoverageObject());
    }

    public function testCoverageFileDoesNotExist()
    {
        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile('no-such-file.cov');

        $this->assertNull($coverageMerger->getCodeCoverageObject());
    }

    /**
     * @return array
     */
    public static function getCoverageFileProvider()
    {
        $version = 'CodeCoverage >4.0';
        $filenames = [
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

    /**
     * @param CoverageMerger $coverageMerger
     *
     * @return CodeCoverage
     */
    private function getCoverage(CoverageMerger $coverageMerger)
    {
        return $this->getObjectValue($coverageMerger, 'coverage');
    }
}
