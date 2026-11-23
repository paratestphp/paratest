<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\custom_coverage_driver;

final class Covered
{
    public function reportedAsCovered(): int
    {
        return 1;
    }

    public function reportedAsNotCovered(): int
    {
        return 2;
    }
}
