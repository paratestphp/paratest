<?php

declare(strict_types=1);

namespace ParaTest\Coverage;

use PHPUnit\TextUI\XmlConfiguration\CodeCoverage\CodeCoverage as CodeCoverageConfiguration;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Report\Cobertura;
use SebastianBergmann\CodeCoverage\Report\Crap4j;
use SebastianBergmann\CodeCoverage\Report\Html;
use SebastianBergmann\CodeCoverage\Report\Html\Colors;
use SebastianBergmann\CodeCoverage\Report\Html\CustomCssFile;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as HtmlReport;
use SebastianBergmann\CodeCoverage\Report\PHP;
use SebastianBergmann\CodeCoverage\Report\Text;
use SebastianBergmann\CodeCoverage\Report\Thresholds;
use SebastianBergmann\CodeCoverage\Report\Xml\Facade as XmlReport;
use SebastianBergmann\CodeCoverage\Version;

/** @internal */
final class CoverageReporter
{
    public function __construct(
        private CodeCoverage $coverage,
        private ?CodeCoverageConfiguration $codeCoverageConfiguration
    ) {
    }

    /**
     * Generate clover coverage report.
     *
     * @param string $target Report filename
     */
    public function clover(string $target): void
    {
        $clover = new Clover();
        $clover->process($this->coverage, $target);
    }

    public function cobertura(string $target): void
    {
        $clover = new Cobertura();
        $clover->process($this->coverage, $target);
    }

    /**
     * Generate Crap4J XML coverage report.
     *
     * @param string $target Report filename
     */
    public function crap4j(string $target): void
    {
        $xml = new Crap4j();
        if ($this->codeCoverageConfiguration !== null && $this->codeCoverageConfiguration->hasCrap4j()) {
            $xml = new Crap4j($this->codeCoverageConfiguration->crap4j()->threshold());
        }

        $xml->process($this->coverage, $target);
    }

    /**
     * Generate html coverage report.
     *
     * @param string $target Report filename
     */
    public function html(string $target): void
    {
        $defaultColors     = Colors::default();
        $defaultThresholds = Thresholds::default();
        $customCssFile = CustomCssFile::default();
        $hasHtml = $this->codeCoverageConfiguration !== null && $this->codeCoverageConfiguration->hasHtml();

        $html = new Html\Facade(
            ' and ParaTest',
            Colors::from(
                $hasHtml ? $this->codeCoverageConfiguration->html()->colorSuccessLow() : $defaultColors->successLow(),
                $hasHtml ? $this->codeCoverageConfiguration->html()->colorSuccessMedium() : $defaultColors->successMedium(),
                $hasHtml ? $this->codeCoverageConfiguration->html()->colorSuccessHigh() : $defaultColors->successHigh(),
                $hasHtml ? $this->codeCoverageConfiguration->html()->colorWarning() : $defaultColors->warning(),
                $hasHtml ? $this->codeCoverageConfiguration->html()->colorDanger() : $defaultColors->danger(),
            ),
            Thresholds::from(
                $hasHtml ? $this->codeCoverageConfiguration->html()->lowUpperBound() : $defaultThresholds->lowUpperBound(),
                $hasHtml ? $this->codeCoverageConfiguration->html()->highLowerBound() : $defaultThresholds->highLowerBound(),
            ),
            $hasHtml && $this->codeCoverageConfiguration->html()->hasCustomCssFile()
                ? CustomCssFile::from($this->codeCoverageConfiguration->html()->customCssFile())
                : $customCssFile
        );

        $html->process($this->coverage, $target);
    }

    /**
     * Generate php coverage report.
     *
     * @param string $target Report filename
     */
    public function php(string $target): void
    {
        $php = new PHP();
        $php->process($this->coverage, $target);
    }

    /**
     * Generate text coverage report.
     *
     * @param bool $colors Coverage colors
     */
    public function text(bool $colors): string
    {
        $defaultThresholds = Thresholds::default();
        $hasText = $this->codeCoverageConfiguration !== null && $this->codeCoverageConfiguration->hasText();
        $hasHtml = $this->codeCoverageConfiguration !== null && $this->codeCoverageConfiguration->hasHtml();
        $text    = new Text(
            Thresholds::from(
                $hasHtml ? $this->codeCoverageConfiguration->html()->lowUpperBound() : $defaultThresholds->lowUpperBound(),
                $hasHtml ? $this->codeCoverageConfiguration->html()->highLowerBound() : $defaultThresholds->highLowerBound(),
            ),
            $hasText && $this->codeCoverageConfiguration->text()->showUncoveredFiles(),
            $hasText && $this->codeCoverageConfiguration->text()->showOnlySummary(),
        );

        return $text->process($this->coverage, $colors);
    }

    /**
     * Generate PHPUnit XML coverage report.
     *
     * @param string $target Report filename
     */
    public function xml(string $target): void
    {
        $xml = new XmlReport(Version::id());
        $xml->process($this->coverage, $target);
    }
}
