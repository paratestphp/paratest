<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Suite;

class SuiteTest extends \ParaTest\Tests\TestBase
{
    protected $suite;

    public function setUp(): void
    {
        $this->suite = new Suite('/path/to/UnitTest.php', []);
    }

    public function testConstruction()
    {
        $this->assertNull($this->getObjectValue($this->suite, 'process'));
    }
}
