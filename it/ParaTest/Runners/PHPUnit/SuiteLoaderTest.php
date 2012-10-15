<?php namespace ParaTest\Runners\PHPUnit;

class SuiteLoaderTest extends \TestBase
{
    protected $loader;
    protected $files;
    protected $testDir;

    public function setUp()
    {
        $this->loader = new SuiteLoader();
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

    public function testLoadDirGetsPathOfAllTests()
    {
        $this->loader->loadDir($this->testDir);
        $loaded = $this->getObjectValue($this->loader, 'loadedSuites');
        $this->assertEquals(sort($this->files), sort($loaded));
    }

    public function testLoadDirStoresParallelSuitesWithPathAsKeys()
    {
        $keys = array(
            $this->files[0],
            $this->files[2],
            $this->files[3],
            $this->files[5]);

        $this->loader->loadDir($this->testDir);

        $paraSuites = $this->getObjectValue($this->loader, 'parallelSuites');
        $this->assertEquals($keys, array_keys($paraSuites));

        return $paraSuites;
    }

    /**
     * @depends testLoadDirStoresParallelSuitesWithPathAsKeys
     */
    public function testFirstParallelSuiteHasCorrectFunctions($paraSuites)
    {
        $first = array_shift($paraSuites);
        $functions = $first->getFunctions();
        $this->assertEquals(3, sizeof($functions));
        $this->assertEquals('testTruth', $functions[0]->getName());
        $this->assertEquals('testFalsehood', $functions[1]->getName());
        $this->assertEquals('testArrayLength', $functions[2]->getName());
    }

    /**
     * @depends testLoadDirStoresParallelSuitesWithPathAsKeys
     */
    public function testSecondParallelSuiteHasCorrectFunctions($paraSuites)
    {
        $second = next($paraSuites);
        $functions = $second->getFunctions();
        $this->assertEquals(2, sizeof($functions));
        $this->assertEquals('testTruth', $functions[0]->getName());
        $this->assertEquals('isItFalse', $functions[1]->getName());
    }

    public function testLoadDirStoresSerialSuitesWithPathAsKeys()
    {
        $keys = array(
            $this->files[1],
            $this->files[4],
            $this->files[6]);

        $this->loader->loadDir($this->testDir);

        $serialSuites = $this->getObjectValue($this->loader, 'serialSuites');
        $this->assertEquals($keys, array_keys($serialSuites));

        return $serialSuites;
    }

    /**
     * @depends testLoadDirStoresSerialSuitesWithPathAsKeys
     */
    public function testFirstSerialSuiteHasCorrectFunctions($serialSuites)
    {
        $first = array_shift($serialSuites);
        $functions = $first->getFunctions();
        $this->assertEquals(3, sizeof($functions));
        $this->assertEquals('testTruth', $functions[0]->getName());
        $this->assertEquals('testFalsehood', $functions[1]->getName());
        $this->assertEquals('testArrayLength', $functions[2]->getName());
    }
}