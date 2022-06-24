<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Coverage;

use ParaTest\Coverage\CoverageReporter;
use ParaTest\Tests\TestBase;
use PHPUnit\TextUI\XmlConfiguration\Loader;
use PHPUnit\Util\Xml;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;

use function uniqid;

/**
 * @internal
 *
 * @covers \ParaTest\Coverage\CoverageReporter
 */
final class CoverageReporterTest extends TestBase
{
    private CoverageReporter $coverageReporter;

    protected function setUpTest(): void
    {
        static::skipIfCodeCoverageNotEnabled();

        $this->createCoverageReporter('phpunit-fully-configured.xml');
    }

    private function createCoverageReporter(string $fixtureFile): void
    {
        $filter = new Filter();
        $filter->includeFile(__FILE__);
        $codeCoverage = new CodeCoverage((new Selector())->forLineCoverage($filter), $filter);
        $codeCoverage->append(RawCodeCoverageData::fromXdebugWithoutPathCoverage([
            __FILE__ => [__LINE__ - 1 => 1],
        ]), uniqid('test_'));

        $configuration = (new Loader())->load($this->fixture($fixtureFile));

        $this->coverageReporter = new CoverageReporter($codeCoverage, $configuration->codeCoverage());
    }

    public function testGenerateClover(): void
    {
        $target = $this->tmpDir . DS . 'clover';

        $this->coverageReporter->clover($target);

        static::assertFileExists($target);

        $reportXml = (new Xml\Loader())->loadFile($target);
        static::assertTrue($reportXml->hasChildNodes(), 'Incorrect clover report xml was generated');
    }

    public function testGenerateCobertura(): void
    {
        $target = $this->tmpDir . DS . 'cobertura';

        $this->coverageReporter->cobertura($target);

        static::assertFileExists($target);

        $reportXml = (new Xml\Loader())->loadFile($target);
        static::assertTrue($reportXml->hasChildNodes(), 'Incorrect Cobertura report xml was generated');
    }

    public function testGenerateCrap4j(): void
    {
        $target = $this->tmpDir . DS . 'crap4j';

        $this->coverageReporter->crap4j($target);

        static::assertFileExists($target);

        $reportXml = (new Xml\Loader())->loadFile($target);
        static::assertTrue($reportXml->hasChildNodes(), 'Incorrect crap4j report xml was generated');
        $documentElement = $reportXml->documentElement;
        static::assertNotNull($documentElement);
        static::assertEquals('crap_result', $documentElement->tagName);
    }

    public function testGenerateHtml(): void
    {
        $target = $this->tmpDir . DS . 'html';

        $this->coverageReporter->html($target);

        static::assertFileExists($target);
        static::assertFileExists($target . DS . 'index.html', 'Index html file was not generated');
    }

    public function testGeneratePhp(): void
    {
        $target = $this->tmpDir . DS . 'php';

        $this->coverageReporter->php($target);

        static::assertFileExists($target);
    }

    /** @dataProvider generateTextProvider */
    public function testGenerateText(string $fixtureFile, string $expectedContainedString): void
    {
        $this->createCoverageReporter($fixtureFile);
        $output = $this->coverageReporter->text(false);

        static::assertStringContainsString($expectedContainedString, $output);
    }

    /** @return string[][] */
    public function generateTextProvider(): array
    {
        return [
            'showOnlySummary = false' => [
                'fixtureFile' => 'phpunit-fully-configured.xml',
                'expectedContainedString' => 'Code Coverage Report:',
            ],
            'showOnlySummary = true' => [
                'fixtureFile' => 'phpunit-coverage-text-show-only-summary.xml',
                'expectedContainedString' => 'Code Coverage Report Summary:',
            ],
        ];
    }

    /** @dataProvider generateTextWithColorsProvider */
    public function testGenerateTextWithColors(string $fixtureFile, string $expectedPattern): void
    {
        $this->createCoverageReporter($fixtureFile);
        $output = $this->coverageReporter->text(true);

        static::assertMatchesRegularExpression($expectedPattern, $output);
    }

    /** @return string[][] */
    public function generateTextWithColorsProvider(): array
    {
        return [
            'showOnlySummary = false' => [
                'fixtureFile' => 'phpunit-fully-configured.xml',
                'expectedPattern' => '~\\e\[(?:\d+;){0,1}\d+;\d+mCode Coverage Report:~',
            ],
            'showOnlySummary = true' => [
                'fixtureFile' => 'phpunit-coverage-text-show-only-summary.xml',
                'expectedPattern' => '~\\e\[(?:\d+;){0,1}\d+;\d+mCode Coverage Report Summary:~',
            ],
        ];
    }

    /** @dataProvider generateTextCoveredFilesProvider */
    public function testGenerateTextCoveredFiles(string $expectedPattern, bool $colors): void
    {
        $this->createCoverageReporter('phpunit-fully-configured.xml');
        $output = $this->coverageReporter->text($colors);

        static::assertMatchesRegularExpression($expectedPattern, $output);
    }

    /** @return  array<string, array<string, bool|string>> */
    public function generateTextCoveredFilesProvider(): array
    {
        return [
            'colors = false' => [
                'expectedPattern' => '~Methods:\s*\d+\.\d{2}%\s*\(\s*\d+/\s*\d+\)'
                    . '\s*Lines:\s*\d+\.\d{2}%\s*\(\s*\d+/\s*\d+\)~',
                'colors' => false,
            ],
            'colors = true' => [
                'expectedPattern' => '~\\e\[\d+;\d+mMethods:\s\d+\.\d{2}%\s\(\s*\d+/\s*\d+\)\\e\[0m'
                    . '\s*\\e\[(?:\d+;){0,1}\d+;\d+mLines:\s*\d+\.\d{2}%\s*\(\s*\d+/\s*\d+\)\\e\[0m~',
                'colors' => true,
            ],
        ];
    }

    public function testGenerateXml(): void
    {
        $target = $this->tmpDir . DS . 'xml';

        $this->coverageReporter->xml($target);

        static::assertFileExists($target);
        static::assertFileExists($target . DS . 'index.xml', 'Index xml file was not generated');

        $reportXml = (new Xml\Loader())->loadFile($target . DS . 'index.xml');
        static::assertTrue($reportXml->hasChildNodes(), 'Incorrect xml was generated');
    }
}
