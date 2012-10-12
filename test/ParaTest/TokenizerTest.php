<?php namespace ParaTest;

class TokenizerTest extends \TestBase
{
    protected $tokenizer;

    public function setUp()
    {
        $this->tokenizer = new Tokenizer($this->pathToFixture('tests' . DS . 'UnitTestWithMethodAnnotationsTest.php'));
    }

    public function testGetTokensIsNotEmpty()
    {
        $tokens = $this->tokenizer->getTokens();
        $this->assertTrue(sizeof($tokens) > 0);
    }

    public function testGetFunctionsAnnotatedWithReturnsCorrectFunctions()
    {
        $funcs = $this->tokenizer->getFunctionsAnnotatedWith('runParallel');
        $this->assertEquals('testFalsehood', $funcs[0]);
        $this->assertEquals('testArrayLength', $funcs[1]);
    }

    public function testGetFunctionReturnsAllFunctions()
    {
        $funcs = $this->tokenizer->getFunctions();
        $this->assertEquals('testTruth', $funcs[0]);
        $this->assertEquals('testFalsehood', $funcs[1]);
        $this->assertEquals('testArrayLength', $funcs[2]);
    }

    public function testGetClassAnnotatedWithReturnsClasses()
    {
        $tokenizer = new Tokenizer($this->pathToFixture('tests' . DS . 'UnitTestWithClassAnnotationTest.php'));
        $classes = $tokenizer->getClassesAnnotatedWith('runParallel');
        $this->assertEquals(1, sizeof($classes));
        $this->assertEquals('UnitTestWithClassAnnotationTest', $classes[0]);
    }

    public function testGetFunctionsWillReturnEmptyListSecondTime()
    {
        $funcs = $this->tokenizer->getFunctions();
        $this->assertEquals(3, sizeof($funcs));
        $funcs = $this->tokenizer->getFunctions();
        $this->assertEquals(0, sizeof($funcs));
    }

    public function testGetFunctionsWillReturnFullListSecondTimeAfterRewind()
    {
        $funcs = $this->tokenizer->getFunctions();
        $this->assertEquals(3, sizeof($funcs));
        $this->tokenizer->rewind();
        $funcs = $this->tokenizer->getFunctions();
        $this->assertEquals(3, sizeof($funcs));
    }    
}