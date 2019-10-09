<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Parser;

use ParaTest\Parser\ParsedClass;
use ParaTest\Parser\Parser;

class GetClassTest extends \ParaTest\Tests\TestBase
{
    public function testPreviouslyLoadedTestClassCanBeParsed()
    {
        $testFile = $this->fixture('passing-tests/PreviouslyLoadedTest.php');
        require_once $testFile;

        $class = $this->parseFile($testFile);
        $this->assertEquals('PreviouslyLoadedTest', $class->getName());
    }

    public function testParsedClassHasName()
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'));
        $this->assertEquals('Fixtures\\Tests\\UnitTestWithClassAnnotationTest', $class->getName());
    }

    public function testParsedAnonymousClassNameHasNoNullByte()
    {
        $class = $this->parseFile($this->fixture('failing-tests/AnonymousClass.inc'));
        $this->assertStringNotContainsString("\x00", $class->getName());
    }

    public function testParsedClassHasDocBlock()
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'));
        $this->assertEquals('/**
 * @runParallel
 * @pizzaBox
 */', $class->getDocBlock());
    }

    public function testParsedClassHasNamespace()
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'));
        $this->assertEquals('Fixtures\\Tests', $class->getNamespace());
    }

    public function testParsedClassHasCorrectNumberOfTestMethods()
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'));
        $this->assertCount(4, $class->getMethods());
    }

    public function testParsedClassWithParentHasCorrectNumberOfTestMethods()
    {
        $class = $this->parseFile($this->fixture('failing-tests/UnitTestWithErrorTest.php'));
        $this->assertCount(4, $class->getMethods());
    }

    /**
     * Parses a test case and returns the test class.
     *
     * @param mixed $path
     *
     * @return ParsedClass
     */
    protected function parseFile($path)
    {
        $parser = new Parser($path);

        return $parser->getClass();
    }
}
