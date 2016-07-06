<?php

use Composer\Semver\Semver;
use ParaTest\Coverage\CoverageMerger;
use SebastianBergmann\CodeCoverage\CodeCoverage;

class CoverageMergerTest extends TestBase
{

    /**
     * Directory to store coverage files
     *
     * @var string
     */
    private $targetDir;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
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
    protected function tearDown()
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

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp /Coverage file .*? is empty. This means a PHPUnit process has crashed./
     */
    public function testCoverageFileIsEmpty()
    {
        $filename = $this->copyCoverageFile('coverage-tests/empty_test.cov', $this->targetDir);

        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile($filename);
    }

    public function testCoverageFileIsNull()
    {
        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile(null);

        static::assertAttributeSame(null, 'coverage', $coverageMerger, 'No code coverage object was created');
    }

    public function testCoverageFileDoesNotExist()
    {
        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile('no-such-file.cov');

        static::assertAttributeSame(null, 'coverage', $coverageMerger, 'No code coverage object was created');
    }

    /**
     * @return array
     */
    public static function getCoverageFileProvider()
    {
        $version = 'CodeCoverage >4.0';
        $filenames = array(
            'coverage-tests/runner_test.cov',
            'coverage-tests/result_printer_test.cov',
        );
        $coverageClass = 'SebastianBergmann\\CodeCoverage\\CodeCoverage';
        if (class_exists('PHP_CodeCoverage')) {
            $version = 'Legacy CodeCoverage';
            $filenames = array(
                'coverage-tests/runner_test.cov4',
                'coverage-tests/result_printer_test.cov4',
            );
            $coverageClass = 'PHP_CodeCoverage';
            if (Semver::satisfies(static::getPhpUnitVersion(), '3.7.*')) {
                $version = 'PHPUnit 3.7';
                $filenames = array(
                    'coverage-tests/runner_test.cov3',
                    'coverage-tests/result_printer_test.cov3',
                );
            }
        }

        return array(
            $version => array(
                'filenames' => $filenames,
                'expected coverage class' => $coverageClass,
            ),
        );
    }

    /**
     * @param CoverageMerger $coverageMerger
     * @return CodeCoverage|\PHP_CodeCoverage
     */
    private function getCoverage(CoverageMerger $coverageMerger)
    {
        return $this->getObjectValue($coverageMerger, 'coverage');
    }


}
