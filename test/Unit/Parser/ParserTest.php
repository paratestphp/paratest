<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Parser;

use InvalidArgumentException;
use ParaTest\Parser\NoClassInFileException;
use ParaTest\Parser\ParsedClass;
use ParaTest\Parser\Parser;
use ParaTest\Tests\fixtures\failing_tests\UnitTestWithClassAnnotationTest;
use ParaTest\Tests\fixtures\passing_tests\PreviouslyLoadedTest;
use ParaTest\Tests\fixtures\special_classes\SomeNamespace\ParserTestClassTest;
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
        $fileWithoutAClass = $this->fixture('warning_tests' . DS . 'CustomAbstractTest.php');

        $this->expectException(NoClassInFileException::class);

        new Parser($fileWithoutAClass);
    }

    public function testPrefersClassByFileName(): void
    {
        $class = $this->parseFile($this->fixture('special_classes' . DS . 'SomeNamespace' . DS . 'ParserTestClassTest.php'));
        static::assertEquals(ParserTestClassTest::class, $class->getName());
    }

    public function testPreviouslyLoadedTestClassCanBeParsed(): void
    {
        $class = $this->parseFile($this->fixture('passing_tests' . DS . 'PreviouslyLoadedTest.php'));
        static::assertEquals(PreviouslyLoadedTest::class, $class->getName());
    }

    public function testParsedClassHasName(): void
    {
        $class = $this->parseFile($this->fixture('failing_tests' . DS . 'UnitTestWithClassAnnotationTest.php'));
        static::assertEquals(UnitTestWithClassAnnotationTest::class, $class->getName());
    }

    public function testParsedClassHasDocBlock(): void
    {
        $class = $this->parseFile($this->fixture('failing_tests' . DS . 'UnitTestWithClassAnnotationTest.php'));
        static::assertEquals('/**
 * @runParallel
 * @pizzaBox
 */', $class->getDocBlock());
    }

    public function testParsedClassHasNamespace(): void
    {
        $class = $this->parseFile($this->fixture('failing_tests' . DS . 'UnitTestWithClassAnnotationTest.php'));
        static::assertEquals('ParaTest\\Tests\\fixtures\\failing_tests', $class->getNamespace());
    }

    public function testParsedClassHasCorrectNumberOfTestMethods(): void
    {
        $class = $this->parseFile($this->fixture('failing_tests' . DS . 'UnitTestWithClassAnnotationTest.php'));
        static::assertCount(4, $class->getMethods());
    }

    public function testParsedClassWithParentHasCorrectNumberOfTestMethods(): void
    {
        $class = $this->parseFile($this->fixture('failing_tests' . DS . 'UnitTestWithErrorTest.php'));
        static::assertCount(8, $class->getMethods());
    }

    /**
     * Parses a test case and returns the test class.
     */
    private function parseFile(string $path): ParsedClass
    {
        return (new Parser($path))->getClass();
    }
}
