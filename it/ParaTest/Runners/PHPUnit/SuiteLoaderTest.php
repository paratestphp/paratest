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

    public function testLoadDirGetsPathOfAllTestsWithKeys()
    {
        $this->loader->loadDir($this->testDir);
        $loaded = $this->getObjectValue($this->loader, 'loadedSuites');
        $this->assertEquals(sort($this->files), sort(array_keys($loaded)));
        return $loaded;
    }

    /**
     * @depends testLoadDirGetsPathOfAllTestsWithKeys
     */
    public function testFirstParallelSuiteHasCorrectFunctions($paraSuites)
    {
        $first = array_shift($paraSuites);
        $functions = $first->getFunctions();
        $this->assertEquals(4, sizeof($functions));
        $this->assertEquals('testTruth', $functions[0]->getName());
        $this->assertEquals('testFalsehood', $functions[1]->getName());
        $this->assertEquals('testArrayLength', $functions[2]->getName());
        $this->assertEquals('itsATest', $functions[3]->getName());
    }

    /**
     * @depends testLoadDirGetsPathOfAllTestsWithKeys
     */
    public function testSecondParallelSuiteHasCorrectFunctions($paraSuites)
    {
        $second = next($paraSuites);
        $functions = $second->getFunctions();
        $this->assertEquals(4, sizeof($functions));
        $this->assertEquals('testTruth', $functions[0]->getName());
        $this->assertEquals('isItFalse', $functions[1]->getName());
        $this->assertEquals('testFalsehood', $functions[2]->getName());
        $this->assertEquals('testArrayLength', $functions[3]->getName());
    }

    public function testGetTestMethodsReturnCorrectNumberOfSuiteTestMethods()
    {
        $this->loader->loadDir($this->testDir);
        $methods = $this->loader->getTestMethods();
        $this->assertEquals(23, sizeof($methods));
        return $methods;
    }

    /**
     * @depends testGetTestMethodsReturnCorrectNumberOfSuiteTestMethods
     */
    public function testTestMethodsShouldBeInstanceOfTestMethod($methods)
    {
        foreach($methods as $method)
            $this->assertInstanceOf('ParaTest\\Runners\\PHPUnit\\TestMethod', $method);
    }
}