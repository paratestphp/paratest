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
}