<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Parser;

use ParaTest\Parser\ParsedClass;
use ParaTest\Parser\Parser;
use ParaTest\Tests\TestBase;

class GetClassTest extends TestBase
{
    public function testPreviouslyLoadedTestClassCanBeParsed(): void
    {
        $testFile = $this->fixture('passing-tests/PreviouslyLoadedTest.php');
        require_once $testFile;

        $class = $this->parseFile($testFile);
        $this->assertEquals('PreviouslyLoadedTest', $class->getName());
    }

    public function testParsedClassHasName(): void
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'));
        $this->assertEquals('Fixtures\\Tests\\UnitTestWithClassAnnotationTest', $class->getName());
    }

    public function testParsedAnonymousClassNameHasNoNullByte(): void
    {
        $class = $this->parseFile($this->fixture('failing-tests/AnonymousClass.inc'));
        $this->assertStringNotContainsString("\x00", $class->getName());
    }

    public function testParsedClassHasDocBlock(): void
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'));
        $this->assertEquals('/**
 * @runParallel
 * @pizzaBox
 */', $class->getDocBlock());
    }

    public function testParsedClassHasNamespace(): void
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'));
        $this->assertEquals('Fixtures\\Tests', $class->getNamespace());
    }

    public function testParsedClassHasCorrectNumberOfTestMethods(): void
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'));
        $this->assertCount(4, $class->getMethods());
    }

    public function testParsedClassWithParentHasCorrectNumberOfTestMethods(): void
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithErrorTest.php'));
        $this->assertCount(4, $class->getMethods());
    }

    /**
     * Parses a test case and returns the test class.
     *
     * @param mixed $path
     */
    protected function parseFile($path): ParsedClass
    {
        $parser = new Parser($path);

        return $parser->getClass();
    }
}
