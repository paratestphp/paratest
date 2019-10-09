<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Parser;

use ParaTest\Parser\Parser;

class ParserTest extends \ParaTest\Tests\TestBase
{
    public function testConstructorThrowsExceptionIfFileNotFound()
    {
        $this->expectException(\InvalidArgumentException::class);

        $parser = new Parser('/path/to/nowhere');
    }

    public function testConstructorThrowsExceptionIfClassNotFoundInFile()
    {
        $this->expectException(\ParaTest\Parser\NoClassInFileException::class);

        $fileWithoutAClass = FIXTURES . DS . 'chdirBootstrap.php';
        $parser = new Parser($fileWithoutAClass);
    }

    public function testPrefersClassByFileName()
    {
        $filename = FIXTURES . DS . 'special-classes' . DS . 'SomeNamespace' . DS . 'ParserTestClass.php';
        $parser = new Parser($filename);
        $this->assertEquals('SomeNamespace\\ParserTestClass', $parser->getClass()->getName());
    }

    public function testClassFallsBackOnExisting()
    {
        $filename = FIXTURES . DS . 'special-classes' . DS . 'NameDoesNotMatch.php';
        $parser = new Parser($filename);
        $this->assertEquals('ParserTestClassFallsBack', $parser->getClass()->getName());
    }
}
