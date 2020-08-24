<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\WrapperRunner;
use ParaTest\Tests\TestBase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\WrapperRunner
 * @requires OSFAMILY Windows
 */
final class WrapperRunnerOnWindowsTest extends TestBase
{
    public function testWrapperRunnerCannotBeUsedOnWindows(): void
    {
        $options = $this->createOptionsFromArgv([]);
        $output  = new BufferedOutput();

        $this->expectException(RuntimeException::class);

        new WrapperRunner($options, $output);
    }
}
