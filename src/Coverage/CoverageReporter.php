<?php

namespace ParaTest\Coverage;

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Report\Html;
use SebastianBergmann\CodeCoverage\Report\PHP;

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
     * Generate clover coverage report
     *
     * @param string $target Report filename
     */
    public function clover($target)
    {
        $clover = new Clover();
        $clover->process($this->coverage, $target);
    }

    /**
     * Generate html coverage report
     *
     * @param string $target Report filename
     */
    public function html($target)
    {
        $html = new Html\Facade();
        $html->process($this->coverage, $target);
    }

    /**
     * Generate php coverage report
     * @param string $target Report filename
     */
    public function php($target)
    {
        $php = new PHP();
        $php->process($this->coverage, $target);
    }
}
