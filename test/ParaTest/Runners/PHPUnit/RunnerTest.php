<?php namespace ParaTest\Runners\PHPUnit;

class PHPUnitRunnerTest extends \TestBase
{
    protected $runner;
    protected $files;
    protected $testDir;

    public function setUp()
    {
        $tests = FIXTURES . DS . 'tests';
        $this->runner = new Runner();
        $this->testDir = $tests;
        $this->files = array_map(function($e) use($tests) { return $tests . DS . $e; }, array(
            'UnitTestWithClassAnnotationTest.php',
            'UnitTestWithMethodAnnotationsTest.php',
            'UnitTestWithErrorTest.php',
            'level1' . DS . 'UnitTestInSubLevelTest.php',
            'level1' . DS . 'AnotherUnitTestInSubLevelTest.php',
            'level1' . DS . 'level2' . DS . 'UnitTestInSubSubLevelTest.php',
            'level1' . DS . 'level2' . DS . 'AnotherUnitTestInSubSubLevelTest.php'
        ));
    }

    public function testConstructor()
    {
        $maxProcs = 4;
        $runner = new Runner(array('maxProcs' => 4, 'suite' => FIXTURES . DS . 'tests'));
        $this->assertEquals(4, $this->getObjectValue($runner, 'maxProcs'));
        $this->assertEquals(FIXTURES . DS . 'tests', $this->getObjectValue($runner, 'suite'));
        
    }

    public function testDefaults()
    {
        $this->assertEquals(5, $this->getObjectValue($this->runner, 'maxProcs'));
        $this->assertEquals(getcwd(), $this->getObjectValue($this->runner, 'suite'));
    }
}