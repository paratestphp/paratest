<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Parser;

use InvalidArgumentException;
use ParaTest\Parser\NoClassInFileException;
use ParaTest\Parser\ParsedClass;
use ParaTest\Parser\Parser;
use ParaTest\Tests\TestBase;

use function uniqid;

/**
 * @covers \ParaTest\Parser\Parser
 */
final class ParserTest extends TestBase
{
    public function testConstructorThrowsExceptionIfFileNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Parser(uniqid('/path/to/nowhere'));
    }

    public function testConstructorThrowsExceptionIfClassNotFoundInFile(): void
    {
        $fileWithoutAClass = FIXTURES . DS . 'fileWithoutClasses.php';

        $this->expectException(NoClassInFileException::class);

        new Parser($fileWithoutAClass);
    }

    public function testExcludeAbstractClasses(): void
    {
        $parser = new Parser($this->fixture('warning-tests' . DS . 'AbstractTest.php'));

        static::assertNull($parser->getClass());
    }

    public function testPrefersClassByFileName(): void
    {
        $class = $this->parseFile($this->fixture('special-classes' . DS . 'SomeNamespace' . DS . 'ParserTestClass.php'));
        static::assertEquals('SomeNamespace\\ParserTestClass', $class->getName());
    }

    public function testClassFallsBackOnExisting(): void
    {
        $filename    = FIXTURES . DS . 'special-classes' . DS . 'NameDoesNotMatch.php';
        $parser      = new Parser($filename);
        $parserClass = $parser->getClass();
        static::assertNotNull($parserClass);
        static::assertEquals('ParserTestClassFallsBack', $parserClass->getName());
    }

    public function testPreviouslyLoadedTestClassCanBeParsed(): void
    {
        $class = $this->parseFile($this->fixture('passing-tests' . DS . 'PreviouslyLoadedTest.php'));
        static::assertEquals('PreviouslyLoadedTest', $class->getName());
    }

    public function testParsedClassHasName(): void
    {
        $class = $this->parseFile($this->fixture('failing-tests' . DS . 'UnitTestWithClassAnnotationTest.php'));
        static::assertEquals('Fixtures\\Tests\\UnitTestWithClassAnnotationTest', $class->getName());
    }

    public function testParsedAnonymousClassNameHasNoNullByte(): void
    {
        $class = $this->parseFile($this->fixture('failing-tests' . DS . 'AnonymousClass.inc'));
        static::assertStringNotContainsString("\x00", $class->getName());
    }

    public function testParsedClassHasDocBlock(): void
    {
        $class = $this->parseFile($this->fixture('failing-tests' . DS . 'UnitTestWithClassAnnotationTest.php'));
        static::assertEquals('/**
 * @runParallel
 * @pizzaBox
 */', $class->getDocBlock());
    }

    public function testParsedClassHasNamespace(): void
    {
        $class = $this->parseFile($this->fixture('failing-tests' . DS . 'UnitTestWithClassAnnotationTest.php'));
        static::assertEquals('Fixtures\\Tests', $class->getNamespace());
    }

    public function testParsedClassHasCorrectNumberOfTestMethods(): void
    {
        $class = $this->parseFile($this->fixture('failing-tests' . DS . 'UnitTestWithClassAnnotationTest.php'));
        static::assertCount(4, $class->getMethods());
    }

    public function testParsedClassWithParentHasCorrectNumberOfTestMethods(): void
    {
        $class = $this->parseFile($this->fixture('failing-tests' . DS . 'UnitTestWithErrorTest.php'));
        static::assertCount(8, $class->getMethods());
    }

    /**
     * Parses a test case and returns the test class.
     */
    private function parseFile(string $path): ParsedClass
    {
        $parser      = new Parser($path);
        $parserClass = $parser->getClass();
        static::assertNotNull($parserClass);

        return $parserClass;
    }
}
