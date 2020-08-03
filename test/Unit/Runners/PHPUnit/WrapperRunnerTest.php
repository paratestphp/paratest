<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\WrapperRunner;
use ParaTest\Tests\TestBase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

final class WrapperRunnerTest extends TestBase
{
    /**
     * @requires OSFAMILY Windows
     */
    public function testWrapperRunnerCannotBeUsedOnWindows(): void
    {
        $options = new Options([]);
        $output  = new BufferedOutput();

        $this->expectException(RuntimeException::class);

        new WrapperRunner($options, $output);
    }
}
