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
        $expected = array(
            new ParsedFunction('/**
     * @group fixtures
     * @pizza
     */', 'public', 'testTruth'),
            new ParsedFunction('/**
     * @group fixtures
     * @pizza
     */', 'public', 'testFalsehood'),
            new ParsedFunction('/**
     * @group fixtures
     */', 'public', 'testArrayLength'),
            new ParsedFunction('', 'private', 'helperFunction')
        );
        $this->assertEquals($expected, $this->class->getFunctions());
    }
}
