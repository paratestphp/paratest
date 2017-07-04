<?php

namespace ParaTest\Runners\PHPUnit;

class SuiteTest extends \TestBase
{
    protected $suite;

    public function setUp()
    {
        $this->suite = new Suite('/path/to/UnitTest.php', []);
    }

    public function testConstruction()
    {
        $this->assertNull($this->getObjectValue($this->suite, 'process'));
    }
}
