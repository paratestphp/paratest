<?php namespace ParaTest\Parser;

class ParserTest_GetClassTest extends \TestBase
{
    protected $parser;

    public function setUp()
    {
        $path = FIXTURES . DS . 'tests' . DS . 'UnitTestWithClassAnnotationTest.php';
        $this->parser = new Parser($path);
        $this->class = $this->parser->getClass();
    }

    public function testParsedClassHasName()
    {
        $this->assertEquals('Fixtures\\Tests\\UnitTestWithClassAnnotationTest', $this->class->getName());
    }

    public function testParsedClassHasDocBlock()
    {
        $this->assertEquals('/**
 * @runParallel
 * @pizzaBox
 */', $this->class->getDocBlock());
    }

    public function testParsedClassHasNamespace()
    {
        $this->assertEquals('Fixtures\\Tests', $this->class->getNamespace());
    }

    public function testParsedClassHasCorrectNumberOfTestMethods()
    {
        $methods = $this->class->getMethods();
        $this->assertEquals(4, sizeof($methods));                        
    }

    public function testParsedClassWithParentHasCorrectNumberOfTestMethods()
    {
        $parser = new Parser(FIXTURES . DS . 'tests' . DS . 'UnitTestWithErrorTest.php');
        $class = $parser->getClass();
        $methods = $class->getMethods();
        $this->assertEquals(4, sizeof($methods));
    }
}