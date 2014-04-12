<?php namespace ParaTest\Runners\PHPUnit;

class SuiteLoaderTest extends \TestBase
{
    /**
     * @var SuiteLoader
     */
    protected $loader;
    protected $files;
    protected $options;
    protected $testDir;

    public function setUp()
    {
        chdir(__DIR__);
        $this->options = new Options(array('group' => 'group1'));
        $this->loader = new SuiteLoader();
        $tests = FIXTURES . DS . 'tests';
        $this->testDir = $tests;
        $this->files = array_map(function($e) use($tests) { return $tests . DS . $e; }, array(
            'EnvironmentTest.php',
            'GroupsTest.php',
            'LegacyNamespaceTest.php',
            'LongRunningTest.php',
            'StopOnFailureTest.php',
            'PreviouslyLoadedTest.php',
            'TestTokenTest.php',
            'UnitTestWithClassAnnotationTest.php',
            'UnitTestWithMethodAnnotationsTest.php',
            'UnitTestWithErrorTest.php',
            'level1' . DS . 'UnitTestInSubLevelTest.php',
            'level1' . DS . 'AnotherUnitTestInSubLevelTest.php',
            'level1' . DS . 'level2' . DS . 'UnitTestInSubSubLevelTest.php',
            'level1' . DS . 'level2' . DS . 'AnotherUnitTestInSubSubLevelTest.php',
        ));
    }

    public function testConstructor()
    {
        $options = new Options(array('group' => 'group1'));
        $loader = new SuiteLoader($options);
        $this->assertEquals($this->options, $this->getObjectValue($loader, 'options'));
    }

    public function testOptionsCanBeNull()
    {
        $loader = new SuiteLoader();
        $this->assertNull($this->getObjectValue($loader, 'options'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testOptionsMustBeInstanceOfOptionsIfNotNull()
    {
        $loader = new SuiteLoader(array('one' => 'two', 'three' => 'foure'));
    }

    /**
     * @expectedException   \InvalidArgumentException
     */
    public function testLoadThrowsExceptionWithInvalidPath()
    {
        $this->loader->load('/path/to/nowhere');
    }

    /**
     * @expectedException   \RuntimeException
     * @expectedExceptionMessage No path or configuration provided (tests must end with Test.php)
     */
    public function testLoadBarePathWithNoPathAndNoConfiguration()
    {
        $this->loader->load();
    }

    public function testLoadSuiteFromConfig()
    {
        $options = new Options(array('configuration' => FIXTURES . DS . 'phpunit.xml.dist'));
        $loader = new SuiteLoader($options);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');
        $this->assertEquals(15, sizeof($files));
    }

    public function testLoadSuiteFromConfigWithMultipleDirs()
    {
        $options = new Options(array('configuration' => FIXTURES . DS . 'phpunit_multidir.xml.dist'));
        $loader = new SuiteLoader($options);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');
        $this->assertEquals(17, sizeof($files));
    }


    /**
     * @expectedException   \RuntimeException
     * @expectedExceptionMessage Suite path ./nope/ could not be found
     */
    public function testLoadSuiteFromConfigWithBadSuitePath()
    {
        $options = new Options(array('configuration' => FIXTURES . DS . 'phpunitbad.xml.dist'));
        $loader = new SuiteLoader($options);
        $loader->load();
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
        $first = $this->suiteByPath('GroupsTest.php', $paraSuites);
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
        $second = $this->suiteByPath('LegacyNamespaceTest.php', $paraSuites);
        $functions = $second->getFunctions();
        $this->assertEquals(0, sizeof($functions));
    }

    /**
     * @depends testLoadDirGetsPathOfAllTestsWithKeys
     */
    public function testThirdParallelSuiteHasCorrectFunctions($paraSuites)
    {
        $third = $this->suiteByPath('LongRunningTest.php', $paraSuites);
        $functions = $third->getFunctions();
        $this->assertEquals(3, sizeof($functions));
        $this->assertEquals('testOne', $functions[0]->getName());
        $this->assertEquals('testTwo', $functions[1]->getName());
        $this->assertEquals('testThree', $functions[2]->getName());
    }

    public function testGetTestMethodsReturnCorrectNumberOfSuiteTestMethods()
    {
        $this->loader->load($this->testDir);
        $methods = $this->loader->getTestMethods();
        $this->assertEquals(39, sizeof($methods));
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

    public function testExecutableTestsForFunctionalModeUse()
    {
        $path = FIXTURES . DS . 'tests' . DS . 'DependsOnChain.php';
        $this->loader->load($path);
        $tests = $this->loader->getTestMethods();
        $this->assertEquals(2, count($tests));
        $testMethod = $tests[0];
        $testMethodName = $this->getObjectValue($testMethod, 'name');
        $this->assertEquals($testMethodName, 'testOneA|testOneBDependsOnA|testOneCDependsOnB');
        $testMethod = $tests[1];
        $testMethodName = $this->getObjectValue($testMethod, 'name');
        $this->assertEquals($testMethodName, 'testTwoA|testTwoBDependsOnA');
    }

    protected function getLoadedPaths($path)
    {
        $this->loader->load($path);
        $loaded = $this->getObjectValue($this->loader, 'loadedSuites');
        $paths = array_keys($loaded);
        return $paths;
    }

    private function suiteByPath($path, array $paraSuites)
    {
        foreach ($paraSuites as $completePath => $suite) {
            if (strstr($completePath, $path)) {
                return $suite;
            }
        }
        throw new \RuntimeException("Suite $path not found.");
    }
}
