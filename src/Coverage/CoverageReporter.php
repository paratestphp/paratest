<?php

declare(strict_types=1);

namespace ParaTest\Coverage;

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Report\Html;
use SebastianBergmann\CodeCoverage\Report\PHP;
use SebastianBergmann\CodeCoverage\Report\Text;
use SebastianBergmann\CodeCoverage\Report\Xml\Facade as XmlReport;
use SebastianBergmann\CodeCoverage\Version;

class CoverageReporter implements CoverageReporterInterface
{
    /**
     * @var CodeCoverage
     */
    private $coverage;

    /**
     * @param CodeCoverage $coverage
     */
    public function __construct(CodeCoverage $coverage)
    {
        $this->coverage = $coverage;
    }

    /**
     * Generate clover coverage report.
     *
     * @param string $target Report filename
     */
    public function clover(string $target)
    {
        $clover = new Clover();
        $clover->process($this->coverage, $target);
    }

    /**
     * Generate html coverage report.
     *
     * @param string $target Report filename
     */
    public function html(string $target)
    {
        $html = new Html\Facade();
        $html->process($this->coverage, $target);
    }

    /**
     * Generate php coverage report.
     *
     * @param string $target Report filename
     */
    public function php(string $target)
    {
        $php = new PHP();
        $php->process($this->coverage, $target);
    }

    /**
     * Generate text coverage report.
     */
    public function text()
    {
        $text = new Text();
        echo $text->process($this->coverage);
    }

    /**
     * Generate PHPUnit XML coverage report.
     *
     * @param string $target Report filename
     */
    public function xml(string $target)
    {
        $xml = new XmlReport(Version::id());
        $xml->process($this->coverage, $target);
    }
}
