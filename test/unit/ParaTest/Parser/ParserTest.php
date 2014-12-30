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

    /**
     * @expectedException  \InvalidArgumentException
     */
    public function testConstructorThrowsExceptionIfClassNotFoundInFile()
    {
        $fileWithoutAClass = FIXTURES . DS . 'chdirBootstrap.php';
        $parser = new Parser($fileWithoutAClass);
    }
}