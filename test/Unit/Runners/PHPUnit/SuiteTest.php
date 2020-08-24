<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Suite;
use ParaTest\Runners\PHPUnit\TestMethod;
use ParaTest\Tests\TestBase;

use function uniqid;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\Suite
 */
final class SuiteTest extends TestBase
{
    public function testConstructor(): void
    {
        $file        = uniqid('pathToFile_');
        $testMethod1 = new TestMethod($file, [], false, TMP_DIR);
        $testMethod2 = new TestMethod($file, [], false, TMP_DIR);
        $testMethods = [$testMethod1, $testMethod2];
        $suite       = new Suite($file, $testMethods, false, TMP_DIR);

        $commandArguments = $suite->commandArguments(uniqid(), [], null);

        static::assertNotContains('--filter', $commandArguments);
        static::assertContains($file, $commandArguments);
        static::assertSame($testMethods, $suite->getFunctions());
        static::assertSame(2, $suite->getTestCount());
    }
}
