<?php namespace ParaTest\Runners\PHPUnit;

class TestBaseClass
{
    protected function helloWorld($a = 0, $b = 0, $c = 0)
    {
        if ($a || $b || $c) {
            return "Hello World $a $b $c";
        }
        return 'Hello World';
    }

    protected static function helloAll()
    {
        return 'Hello All';
    }
}

class TestBaseTest extends \TestBase
{
    public function testCallCallsProtectedMethods()
    {
        $testBaseClass = new TestBaseClass;
        $this->assertEquals('Hello World', $this->call($testBaseClass, 'helloWorld'));
    }

    public function testCallStaticCallsProtectedMethods()
    {
        $testBaseClass = new TestBaseClass;
        $this->assertEquals('Hello All', $this->call($testBaseClass, 'helloAll'));
    }

    public function testCallPassesArguments()
    {
        $testBaseClass = new TestBaseClass;
        $this->assertEquals('Hello World 2 3 4', $this->call($testBaseClass, 'helloWorld', 2, 3, 4));
    }
}
