<?php

namespace ParaTest\Coverage;

/**
 * Coverage reporter for phpunit/php-code-coverage version 3 and older
 */
class CoverageReporterLegacy implements CoverageReporterInterface
{
    /**
     * @var \PHP_CodeCoverage
     */
    private $coverage;

    /**
     * @param \PHP_CodeCoverage $coverage
     */
    public function __construct(\PHP_CodeCoverage $coverage)
    {
        $this->coverage = $coverage;
    }

    /**
     * Generate clover coverage report
     *
     * @param string $target Report filename
     */
    public function clover($target)
    {
        $clover = new \PHP_CodeCoverage_Report_Clover();
        $clover->process($this->coverage, $target);
    }

    /**
     * Generate html coverage report
     *
     * @param string $target Report filename
     */
    public function html($target)
    {
        $html = new \PHP_CodeCoverage_Report_HTML();
        $html->process($this->coverage, $target);
    }

    /**
     * Generate php coverage report
     * @param string $target Report filename
     */
    public function php($target)
    {
        $php = new \PHP_CodeCoverage_Report_PHP();
        $php->process($this->coverage, $target);
    }
}
