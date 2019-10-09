<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\TestMethod;

class TestMethodTest extends \ParaTest\Tests\TestBase
{
    public function testConstructor()
    {
        $testMethod = new TestMethod('pathToFile', ['methodName']);
        $this->assertEquals('pathToFile', $this->getObjectValue($testMethod, 'path'));
    }
}
