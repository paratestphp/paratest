<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Parser;

use InvalidArgumentException;
use ParaTest\Parser\NoClassInFileException;
use ParaTest\Parser\Parser;
use ParaTest\Tests\TestBase;

class ParserTest extends TestBase
{
    public function testConstructorThrowsExceptionIfFileNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $parser = new Parser('/path/to/nowhere');
    }

    public function testConstructorThrowsExceptionIfClassNotFoundInFile(): void
    {
        $this->expectException(NoClassInFileException::class);

        $fileWithoutAClass = FIXTURES . DS . 'chdirBootstrap.php';
        $parser            = new Parser($fileWithoutAClass);
    }

    public function testPrefersClassByFileName(): void
    {
        $filename = FIXTURES . DS . 'special-classes' . DS . 'SomeNamespace' . DS . 'ParserTestClass.php';
        $parser   = new Parser($filename);
        $this->assertEquals('SomeNamespace\\ParserTestClass', $parser->getClass()->getName());
    }

    public function testClassFallsBackOnExisting(): void
    {
        $filename = FIXTURES . DS . 'special-classes' . DS . 'NameDoesNotMatch.php';
        $parser   = new Parser($filename);
        $this->assertEquals('ParserTestClassFallsBack', $parser->getClass()->getName());
    }
}
