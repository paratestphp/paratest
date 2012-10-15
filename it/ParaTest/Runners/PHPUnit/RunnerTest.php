<?php namespace ParaTest\Runners\PHPUnit;

class RunnerTest extends \TestBase
{
    protected $runner;

    public function setUp()
    {
        $this->runner = new Runner(array('suite' => FIXTURES . DS . 'tests'));
    }

    public function testTruth()
    {
        $this->assertTrue(true);
    }
}