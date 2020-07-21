<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\WrapperRunner;

class WrapperRunnerTest extends \ParaTest\Tests\TestBase
{
    /**
     * @requires OSFAMILY Windows
     */
    public function testWrapperRunnerCannotBeUsedOnWindows()
    {
        $this->expectException(\RuntimeException::class);

        new WrapperRunner();
    }
}
