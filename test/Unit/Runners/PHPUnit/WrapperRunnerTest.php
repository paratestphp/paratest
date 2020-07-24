<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\WrapperRunner;
use ParaTest\Tests\TestBase;
use RuntimeException;

class WrapperRunnerTest extends TestBase
{
    /**
     * @requires OSFAMILY Windows
     */
    public function testWrapperRunnerCannotBeUsedOnWindows(): void
    {
        $this->expectException(RuntimeException::class);

        new WrapperRunner();
    }
}
