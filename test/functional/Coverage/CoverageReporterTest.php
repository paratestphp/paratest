<?php

use Composer\Semver\Semver;
use ParaTest\Coverage\CoverageMerger;

class CoverageReporterTest extends TestBase
{
    /**
     * Target directory for reports
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

        $this->targetDir = str_replace('.', '_', uniqid('/tmp/report-', true));
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
     * @dataProvider getReporterProvider
     *
     * @param string[] $coverageFiles
     * @param string $expectedReportClass
     */
    public function testGetReporter(array $coverageFiles, $expectedReportClass)
    {
        $filename1 = $this->copyCoverageFile($coverageFiles[0], $this->targetDir);
        $filename2 = $this->copyCoverageFile($coverageFiles[1], $this->targetDir);

        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile($filename1);
        $coverageMerger->addCoverageFromFile($filename2);

        $reporter = $coverageMerger->getReporter();

        static::assertInstanceOf($expectedReportClass, $reporter);
    }

    /**
     * @dataProvider getReporterProvider
     *
     * @param string[] $coverageFiles
     */
    public function testGeneratePhp(array $coverageFiles)
    {
        $filename1 = $this->copyCoverageFile($coverageFiles[0], $this->targetDir);
        $filename2 = $this->copyCoverageFile($coverageFiles[1], $this->targetDir);

        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile($filename1);
        $coverageMerger->addCoverageFromFile($filename2);

        $target = $this->targetDir.'/coverage.php';

        static::assertFileNotExists($target);

        $coverageMerger->getReporter()->php($target);

        static::assertFileExists($target);
    }

    /**
     * @dataProvider getReporterProvider
     *
     * @param string[] $coverageFiles
     */
    public function testGenerateClover(array $coverageFiles)
    {
        $filename1 = $this->copyCoverageFile($coverageFiles[0], $this->targetDir);
        $filename2 = $this->copyCoverageFile($coverageFiles[1], $this->targetDir);

        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile($filename1);
        $coverageMerger->addCoverageFromFile($filename2);

        $target = $this->targetDir.'/coverage.xml';

        static::assertFileNotExists($target);

        $coverageMerger->getReporter()->clover($target);

        static::assertFileExists($target);

        $reportXml = \PHPUnit_Util_XML::loadFile($target);
        static::assertInstanceOf('DomDocument', $reportXml, 'Incorrect clover report xml was generated');
    }

    /**
     * @dataProvider getReporterProvider
     *
     * @param string[] $coverageFiles
     */
    public function testGenerateHtml(array $coverageFiles)
    {
        $filename1 = $this->copyCoverageFile($coverageFiles[0], $this->targetDir);
        $filename2 = $this->copyCoverageFile($coverageFiles[1], $this->targetDir);

        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile($filename1);
        $coverageMerger->addCoverageFromFile($filename2);

        $target = $this->targetDir.'/coverage';

        static::assertFileNotExists($target);

        $coverageMerger->getReporter()->html($target);

        static::assertFileExists($target);
        static::assertFileExists($target.'/index.html', 'Index html file was not generated');
    }

    /**
     * @return array
     */
    public static function getReporterProvider()
    {
        $version = 'CodeCoverage >4.0';
        $filenames = array(
            'coverage-tests/runner_test.cov',
            'coverage-tests/result_printer_test.cov',
        );
        $reporterClass = 'ParaTest\\Coverage\\CoverageReporter';
        if (class_exists('\PHP_CodeCoverage')) {
            $version = 'Legacy CodeCoverage';
            $filenames = array(
                'coverage-tests/runner_test.cov4',
                'coverage-tests/result_printer_test.cov4',
            );
            $reporterClass = 'ParaTest\\Coverage\\CoverageReporterLegacy';
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
                'expected reporter class' => $reporterClass,
            ),
        );
    }
}
