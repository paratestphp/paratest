<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\TestMethod;
use ParaTest\Tests\TestBase;

class TestMethodTest extends TestBase
{
    public function testConstructor(): void
    {
        $testMethod = new TestMethod('pathToFile', ['methodName']);
        $this->assertEquals('pathToFile', $this->getObjectValue($testMethod, 'path'));
    }
}
