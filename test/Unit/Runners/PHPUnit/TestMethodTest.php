<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\TestMethod;
use ParaTest\Tests\TestBase;

use function uniqid;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\TestMethod
 */
final class TestMethodTest extends TestBase
{
    public function testConstructor(): void
    {
        $file       = uniqid('pathToFile_');
        $testMethod = new TestMethod($file, ['method1', 'method2'], false, TMP_DIR);

        $commandArguments = $testMethod->commandArguments(uniqid(), [], null);

        static::assertContains('--filter', $commandArguments);
        static::assertContains($file, $commandArguments);
        static::assertStringContainsString('method1', $testMethod->getName());
        static::assertStringContainsString('method2', $testMethod->getName());
        static::assertSame(2, $testMethod->getTestCount());
    }
}
