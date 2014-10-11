<?php

namespace ParaTest\Parser;

use ParaTest\Parser\ParsedClass;

class ParserTest_GetClassTest extends \TestBase
{
    public function testPreviouslyLoadedTestClassCanBeParsed()
    {
        $testFile = $this->fixture('passing-tests/PreviouslyLoadedTest.php');
        require_once $testFile;

        $class = $this->parseFile($testFile);
        $this->assertEquals('PreviouslyLoadedTest', $class->getName());
    }

    public function testParsedClassHasName()
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'));
        $this->assertEquals('Fixtures\\Tests\\UnitTestWithClassAnnotationTest', $class->getName());
    }

    public function testParsedClassHasDocBlock()
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'));
        $this->assertEquals('/**
 * @runParallel
 * @pizzaBox
 */', $class->getDocBlock());
    }

    public function testParsedClassHasNamespace()
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'));
        $this->assertEquals('Fixtures\\Tests', $class->getNamespace());
    }

    public function testParsedClassHasCorrectNumberOfTestMethods()
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'));
        $this->assertEquals(4, sizeof($class->getMethods()));
    }

    public function testParsedClassWithParentHasCorrectNumberOfTestMethods()
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithErrorTest.php'));
        $this->assertEquals(4, sizeof($class->getMethods()));
    }

    /**
     * Parses a test case and returns the test class
     * @return ParsedClass
     */
    protected function parseFile($path)
    {
        $parser = new Parser($path);
        return $parser->getClass();
    }
}
