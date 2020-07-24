<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\ExecutableTest;

class ExecutableTestChild extends ExecutableTest
{
    /**
     * Get the expected count of tests to be executed.
     */
    public function getTestCount(): int
    {
        return 1;
    }
}
