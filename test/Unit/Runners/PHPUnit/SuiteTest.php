<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Suite;
use ParaTest\Tests\TestBase;

class SuiteTest extends TestBase
{
    protected $suite;

    public function setUp(): void
    {
        $this->suite = new Suite('/path/to/UnitTest.php', []);
    }

    public function testConstruction(): void
    {
        $this->assertNull($this->getObjectValue($this->suite, 'process'));
    }
}
