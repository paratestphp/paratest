<?php namespace ParaTest\Parser;

class ParserTest extends \TestBase
{
    protected $parser;

    public function setUp()
    {
        $this->parser = new Parser($this->pathToFixture('tests' . DS . 'UnitTestWithClassAnnotationTest.php'));
    }

    public function testGetTokensIsNotEmpty()
    {
        $tokens = $this->getObjectValue($this->parser, 'tokens');
        $this->assertTrue(sizeof($tokens) > 0);
    }

    /**
     * @expectedException   \InvalidArgumentException
     */
    public function testConstructorThrowsExceptionIfFileNotFound()
    {
        $parser = new Parser('/path/to/nowhere');
    }
}