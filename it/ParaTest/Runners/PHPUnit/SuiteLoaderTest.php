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
            'GroupsTest.php',
            'LongRunningTest.php',
            'UnitTestWithClassAnnotationTest.php',
            'UnitTestWithMethodAnnotationsTest.php',
            'UnitTestWithErrorTest.php',
            'level1' . DS . 'UnitTestInSubLevelTest.php',
            'level1' . DS . 'AnotherUnitTestInSubLevelTest.php',
            'level1' . DS . 'level2' . DS . 'UnitTestInSubSubLevelTest.php',
            'level1' . DS . 'level2' . DS . 'AnotherUnitTestInSubSubLevelTest.php'
        ));
    }

    public function testLoadFileGetsPathOfFile()
    {
        $path = FIXTURES . DS . 'tests' . DS . 'UnitTestWithClassAnnotationTest.php';
        $paths = $this->getLoadedPaths($path);
        $this->assertEquals($path, array_shift($paths));
    }

    public function testLoadFileShouldLoadFileWhereNameDoesNotEndInTest()
    {
        $path = FIXTURES . DS . 'tests' . DS . 'TestOfUnits.php';
        $paths = $this->getLoadedPaths($path);
        $this->assertEquals($path, array_shift($paths));
    }

    public function testLoadDirGetsPathOfAllTestsWithKeys()
    {
        $this->loader->load($this->testDir);
        $loaded = $this->getObjectValue($this->loader, 'loadedSuites');
        foreach($loaded as $path => $test)
            $this->assertContains($path, $this->files);
        return $loaded;
    }

    /**
     * @depends testLoadDirGetsPathOfAllTestsWithKeys
     */
    public function testFirstParallelSuiteHasCorrectFunctions($paraSuites)
    {
        $first = array_shift($paraSuites);
        $functions = $first->getFunctions();
        $this->assertEquals(5, sizeof($functions));
        $this->assertEquals('testTruth', $functions[0]->getName());
        $this->assertEquals('testFalsehood', $functions[1]->getName());
        $this->assertEquals('testArrayLength', $functions[2]->getName());
        $this->assertEquals('testStringLength', $functions[3]->getName());
        $this->assertEquals('testAddition', $functions[4]->getName());
    }

    /**
     * @depends testLoadDirGetsPathOfAllTestsWithKeys
     */
    public function testSecondParallelSuiteHasCorrectFunctions($paraSuites)
    {
        $second = next($paraSuites);
        $functions = $second->getFunctions();
        $this->assertEquals(3, sizeof($functions));
        $this->assertEquals('testOne', $functions[0]->getName());
        $this->assertEquals('testTwo', $functions[1]->getName());
        $this->assertEquals('testThree', $functions[2]->getName());
    }

    public function testGetTestMethodsReturnCorrectNumberOfSuiteTestMethods()
    {
        $this->loader->load($this->testDir);
        $methods = $this->loader->getTestMethods();
        $this->assertEquals(31, sizeof($methods));
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

    public function testGetTestMethodsOnlyReturnsMethodsOfGroupIfOptionIsSpecified()
    {
        $options = new Options(array('group' => 'group1'));
        $loader = new SuiteLoader($options);
        $groupsTest = FIXTURES . DS . 'tests' . DS . 'GroupsTest.php';
        $loader->load($groupsTest);
        $methods = $loader->getTestMethods();
        $this->assertEquals(2, sizeof($methods));
        $this->assertEquals('testTruth', $methods[0]->getName());
        $this->assertEquals('testFalsehood', $methods[1]->getName());
    }

    protected function getLoadedPaths($path)
    {
        $this->loader->load($path);
        $loaded = $this->getObjectValue($this->loader, 'loadedSuites');
        $paths = array_keys($loaded);
        return $paths;
    }
}