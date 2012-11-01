<?php namespace ParaTest\Runners\PHPUnit;

class PHPUnitRunnerTest extends \TestBase
{
    protected $runner;
    protected $files;
    protected $testDir;

    public function setUp()
    {
        $this->runner = new Runner();
    }

    public function testConstructor()
    {
        $opts = array('processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true);
        $runner = new Runner($opts);
        $options = $this->getObjectValue($runner, 'options');

        $this->assertEquals(4, $options->processes);
        $this->assertEquals(FIXTURES . DS . 'tests', $options->path);
        $this->assertEquals(array(), $this->getObjectValue($runner, 'pending'));
        $this->assertEquals(array(), $this->getObjectValue($runner, 'running'));
        $this->assertTrue($options->functional);
        //filter out processes and path and phpunit
        $this->assertEquals(array('bootstrap' => 'hello'), $options->filtered);
        $this->assertInstanceOf('ParaTest\\Runners\\PHPUnit\\ResultPrinter', $this->getObjectValue($runner, 'printer'));
    }
}