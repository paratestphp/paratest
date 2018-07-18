<?php

declare(strict_types=1);

namespace ParaTest\Coverage;

interface CoverageReporterInterface
{
    /**
     * Generate clover coverage report.
     *
     * @param string $target Report filename
     */
    public function clover(string $target);

    /**
     * Generate html coverage report.
     *
     * @param string $target Report filename
     */
    public function html(string $target);

    /**
     * Generate php coverage report.
     *
     * @param string $target Report filename
     */
    public function php(string $target);

    /**
     * Generate text coverage report.
     */
    public function text();

    /**
     * Generate PHPUnit XML coverage report.
     *
     * @param string $target Report filename
     */
    public function xml(string $target);
}
