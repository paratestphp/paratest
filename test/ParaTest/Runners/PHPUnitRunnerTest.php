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
        $loaded = $this->getObjectValue($this->runner, 'loadedSuites');
        $this->assertEquals(sort($this->files), sort($loaded));
    }

    /**
     * @expectedException   \InvalidArgumentException
     */
    public function testLoadDirThrowsExceptionWithInvalidPath()
    {
        $this->runner->loadDir('/path/to/nowhere');
    }

    public function testLoadDirStoresParallelSuitesWithPathAsKeys()
    {
        $keys = array(
            $this->files[0],
            $this->files[2],
            $this->files[3],
            $this->files[5]);

        $this->runner->loadDir($this->testDir);

        $paraSuites = $this->getObjectValue($this->runner, 'parallelSuites');
        $this->assertEquals($keys, array_keys($paraSuites));

        return $paraSuites;
    }

    /**
     * @depends testLoadDirStoresParallelSuitesWithPathAsKeys
     */
    public function testFirstParallelSuiteHasCorrectFunctions($paraSuites)
    {
        $first = array_shift($paraSuites);
        $this->assertEquals(3, sizeof($first));
        $this->assertEquals('testTruth', $first[0]);
        $this->assertEquals('testFalsehood', $first[1]);
        $this->assertEquals('testArrayLength', $first[2]);
    }

    /**
     * @depends testLoadDirStoresParallelSuitesWithPathAsKeys
     */
    public function testSecondParallelSuiteHasCorrectFunctions($paraSuites)
    {
        $second = next($paraSuites);
        $this->assertEquals(2, sizeof($second));
        $this->assertEquals('testTruth', $second[0]);
        $this->assertEquals('isItFalse', $second[1]);
    }
}