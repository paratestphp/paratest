<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional\Coverage;

use ParaTest\Coverage\CoverageMerger;
use ParaTest\Coverage\CoverageReporter;
use ParaTest\Tests\TestBase;

class CoverageReporterTest extends TestBase
{
    /**
     * Target directory for reports.
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

        $this->targetDir = str_replace('.', '_', sys_get_temp_dir() . DS . uniqid('paratest-', true));
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

        $target = $this->targetDir . DS . 'coverage.php';

        static::assertFileDoesNotExist($target);

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

        $target = $this->targetDir . DS . 'coverage.xml';

        static::assertFileDoesNotExist($target);

        $coverageMerger->getReporter()->clover($target);

        static::assertFileExists($target);

        $reportXml = \PHPUnit\Util\Xml::loadFile($target);
        static::assertInstanceOf('DomDocument', $reportXml, 'Incorrect clover report xml was generated');
    }

    /**
     * @dataProvider getReporterProvider
     *
     * @param string[] $coverageFiles
     */
    public function testGenerateCrap4J(array $coverageFiles)
    {
        $filename1 = $this->copyCoverageFile($coverageFiles[0], $this->targetDir);
        $filename2 = $this->copyCoverageFile($coverageFiles[1], $this->targetDir);

        $coverageMerger = new CoverageMerger();
        $coverageMerger->addCoverageFromFile($filename1);
        $coverageMerger->addCoverageFromFile($filename2);

        $target = $this->targetDir . DS . 'coverage.xml';

        static::assertFileDoesNotExist($target);

        $coverageMerger->getReporter()->crap4j($target);

        static::assertFileExists($target);

        $reportXml = \PHPUnit\Util\Xml::loadFile($target);
        static::assertInstanceOf('DomDocument', $reportXml, 'Incorrect crap4j report xml was generated');
        static::assertEquals('crap_result', $reportXml->documentElement->tagName);
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

        $target = $this->targetDir . DS . 'coverage';

        static::assertFileDoesNotExist($target);

        $coverageMerger->getReporter()->html($target);

        static::assertFileExists($target);
        static::assertFileExists($target . DS . 'index.html', 'Index html file was not generated');
    }

    /**
     * @return array
     */
    public static function getReporterProvider()
    {
        $version = 'CodeCoverage >4.0';
        $windowsExt = defined('PHP_WINDOWS_VERSION_BUILD') ? '-windows' : '';
        $filenames = [
            'coverage-tests' . DS . 'runner_test' . $windowsExt . '.cov',
            'coverage-tests' . DS . 'result_printer_test' . $windowsExt . '.cov',
        ];
        $reporterClass = CoverageReporter::class;

        return [
            $version => [
                'filenames' => $filenames,
                'expected reporter class' => $reporterClass,
            ],
        ];
    }
}
