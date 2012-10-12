<?php namespace ParaTest\Runners;

class PHPUnitRunnerTest extends \TestBase
{
    protected $runner;

    public function setUp()
    {
        $this->runner = new PHPUnitRunner();
    }

    public function testConstructor()
    {
        $maxProcs = 4;
        $runner = new PHPUnitRunner($maxProcs);
        $this->assertEquals(4, $this->getObjectValue($runner, 'maxProcs'));
    }

    public function testMaxProcsDefaultsTo5()
    {
        $procs = $this->getObjectValue($this->runner, 'maxProcs');
        $this->assertEquals(5, $procs);
    }

    public function testLoadDirGetsPathOfAllTests()
    {
        $tests = FIXTURES . DS . 'tests';
        $files = array_map(function($e) use($tests) { return $tests . DS . $e; }, array(
            'UnitTestWithClassAnnotationTest.php',
            'UnitTestWithMethodAnnotationsTest.php',
            'UnitTestWithErrorTest.php',
            'level1' . DS . 'UnitTestInSubLevelTest.php',
            'level1' . DS . 'AnotherUnitTestInSubLevelTest.php',
            'level1' . DS . 'level2' . DS . 'UnitTestInSubSubLevelTest.php',
            'level1' . DS . 'level2' . DS . 'AnotherUnitTestInSubSubLevelTest.php'
        ));
        $this->runner->loadDir($tests);
        $loaded = $this->getObjectValue($this->runner, 'loadedTests');
        $this->assertEquals(sort($files), sort($loaded));
    }

    /**
     * @expectedException   \InvalidArgumentException
     */
    public function testLoadPathThrowsExceptionWithInvalidPath()
    {
        $this->runner->loadDir('/path/to/nowhere');
    }
}