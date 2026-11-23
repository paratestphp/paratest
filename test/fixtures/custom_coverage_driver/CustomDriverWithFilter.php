<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\custom_coverage_driver;

use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Filter;

/** Like the bundled drivers, requires the Filter in its constructor */
final class CustomDriverWithFilter extends Driver
{
    public function __construct(public readonly Filter $filter)
    {
    }

    public function name(): string
    {
        return 'CustomDriverWithFilter';
    }

    public function version(): string
    {
        return '4.5.6';
    }

    public function start(): void
    {
    }

    public function stop(): RawCodeCoverageData
    {
        return RawCodeCoverageData::fromLineCoverage([]);
    }
}
