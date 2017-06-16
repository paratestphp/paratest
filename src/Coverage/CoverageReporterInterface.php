<?php

namespace ParaTest\Coverage;

interface CoverageReporterInterface
{
    /**
     * Generate clover coverage report
     *
     * @param string $target Report filename
     */
    public function clover($target);

    /**
     * Generate html coverage report
     *
     * @param string $target Report filename
     */
    public function html($target);

    /**
     * Generate php coverage report
     * @param string $target Report filename
     */
    public function php($target);
}
