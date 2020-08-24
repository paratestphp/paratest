<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\FullSuite;
use ParaTest\Tests\TestBase;

use function uniqid;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\FullSuite
 */
final class FullSuiteTest extends TestBase
{
    public function testPrepareTheFullSuiteAsArguments(): void
    {
        $name      = uniqid('Suite_');
        $fullSuite = new FullSuite($name, false, TMP_DIR);

        $commandArguments = $fullSuite->commandArguments(uniqid(), [], null);

        static::assertContains('--testsuite', $commandArguments);
        static::assertContains($name, $commandArguments);
        static::assertSame(1, $fullSuite->getTestCount());
    }
}
