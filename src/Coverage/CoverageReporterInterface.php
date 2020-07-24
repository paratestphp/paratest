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
    public function clover(string $target): void;

    /**
     * Generate Crap4J XML coverage report.
     *
     * @param string $target Report filename
     */
    public function crap4j(string $target): void;

    /**
     * Generate html coverage report.
     *
     * @param string $target Report filename
     */
    public function html(string $target): void;

    /**
     * Generate php coverage report.
     *
     * @param string $target Report filename
     */
    public function php(string $target): void;

    /**
     * Generate text coverage report.
     */
    public function text(): void;

    /**
     * Generate PHPUnit XML coverage report.
     *
     * @param string $target Report filename
     */
    public function xml(string $target): void;
}
