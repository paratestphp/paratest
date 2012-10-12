<?php namespace ParaTest\Runners;

class PHPUnitRunnerTest extends \TestBase
{
    protected $runner;
    protected $files;
    protected $testDir;

    public function setUp()
    {
        $this->runner = new PHPUnitRunner();
        $tests = FIXTURES . DS . 'tests';
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
        $this->runner->loadDir($this->testDir);
        $loaded = $this->getObjectValue($this->runner, 'loadedTests');
        $this->assertEquals(sort($this->files), sort($loaded));
    }

    /**
     * @expectedException   \InvalidArgumentException
     */
    public function testLoadDirThrowsExceptionWithInvalidPath()
    {
        $this->runner->loadDir('/path/to/nowhere');
    }

    /*public function testLoadDirStoresParallelSuites()
    {
        $keys = array(
            'UnitTestWithClassAnnotationTest',
            'UnitTestWithErrorTest',
            'UnitTestInSubLevelTest',
            'UnitTestInSubSubLevelTest');
        $paraSuites = $this->getObjectValue($this->runner, 'parallelSuites');
    }*/
}