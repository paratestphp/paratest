<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\custom_coverage_driver;

use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\Driver;

/** Reports a fixed line as executed, regardless of the code actually run */
final class CustomDriver extends Driver
{
    public function name(): string
    {
        return 'CustomDriver';
    }

    public function version(): string
    {
        return '1.2.3';
    }

    public function start(): void
    {
    }

    public function stop(): RawCodeCoverageData
    {
        return RawCodeCoverageData::fromLineCoverage([
            __DIR__ . '/Covered.php' => [11 => self::LINE_EXECUTED],
        ]);
    }
}
