<?php namespace ParaTest\Parser;

class ParserTest extends \TestBase
{
    /**
     * @expectedException   \InvalidArgumentException
     */
    public function testConstructorThrowsExceptionIfFileNotFound()
    {
        $parser = new Parser('/path/to/nowhere');
    }

    public function testFileNameResolvesToNamespacedClass()
    {
        $parser = new Parser(__FILE__);
        $class = $parser->getClass();
        $this->assertEquals('ParaTest\Parser\ParserTest', $class->getName());
    }

    public function testFileNameResolvesToNonNamespacedClass()
    {
        $parser = new Parser(PARATEST_ROOT . DS . 'test' . DS . 'TestBase.php');
        $class = $parser->getClass();
        $this->assertEquals('TestBase', $class->getName());
    }
}