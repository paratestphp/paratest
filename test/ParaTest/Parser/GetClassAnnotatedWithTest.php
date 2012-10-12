<?php namespace ParaTest\Parser;

class GetClassAnnotatedWithTest extends \TestBase
{
    protected $parser;
    protected $class;

    public function setUp()
    {
        $this->parser = new Parser($this->pathToFixture('tests' . DS . 'UnitTestWithClassAnnotationTest.php'));
        $this->class = $this->parser->getClassAnnotatedWith('runParallel');
    }

    public function testParsedClassHasNameProperty()
    {
        $this->assertEquals('UnitTestWithClassAnnotationTest', $this->class->getName());
    }

    public function testParsedClassHasCompleteDocBlock()
    {
        $this->assertEquals('/**
 * @runParallel
 * @pizzaBox
 */', $this->class->getDocBlock());
    }

    public function testParsedClassHasCollectionOfParsedFunctions()
    {
        $functions = $this->class->getFunctions();
        $this->assertEquals('testTruth', $functions[0]->getName());
        $this->assertEquals('testFalsehood', $functions[1]->getName());
        $this->assertEquals('testArrayLength', $functions[2]->getName());
        $this->assertEquals('helperFunction', $functions[3]->getName());
    }
}
