<?php

namespace ParaTest\Parser;

use ParaTest\Parser\ParsedClass;

class ParserTest_GetClassTest extends \TestBase
{
    protected $parser;

    /**
     * @var ParsedClass
     */
    protected $class;

    protected $normalFile;

    protected $loadedFile;

    protected $errorFile;

    protected function setUp() {
        $testDir = FIXTURES . DS . 'tests';
        $this->normalFile = $testDir . DS . 'UnitTestWithClassAnnotationTest.php';
        $this->loadedFile = $testDir . DS . 'PreviouslyLoadedTest.php';
        $this->errorFile  = $testDir . DS . 'UnitTestWithErrorTest.php';
    }

    protected function parseFile($path)
    {
        $this->parser = new Parser($path);
        $this->class = $this->parser->getClass();
    }

    public function testPreviouslyLoadedTestClassCanBeParsed()
    {
        require_once $this->loadedFile;

        $this->parseFile($this->loadedFile);

        $this->assertEquals('PreviouslyLoadedTest', $this->class->getName());
    }

    public function testParsedClassHasName()
    {
        $this->parseFile($this->normalFile);
        $this->assertEquals('Fixtures\\Tests\\UnitTestWithClassAnnotationTest', $this->class->getName());
    }

    public function testParsedClassHasDocBlock()
    {
        $this->parseFile($this->normalFile);
        $this->assertEquals('/**
 * @runParallel
 * @pizzaBox
 */', $this->class->getDocBlock());
    }

    public function testParsedClassHasNamespace()
    {
        $this->parseFile($this->normalFile);
        $this->assertEquals('Fixtures\\Tests', $this->class->getNamespace());
    }

    public function testParsedClassHasCorrectNumberOfTestMethods()
    {
        $this->parseFile($this->normalFile);
        $this->assertEquals(4, sizeof($this->class->getMethods()));
    }

    public function testParsedClassWithParentHasCorrectNumberOfTestMethods()
    {
        $this->parseFile($this->errorFile);
        $this->assertEquals(4, sizeof($this->class->getMethods()));
    }
}