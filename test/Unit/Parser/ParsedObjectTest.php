<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Parser;

use ParaTest\Parser\ParsedClass;
use ParaTest\Tests\TestBase;

/**
 * @coversNothing
 */
final class ParsedObjectTest extends TestBase
{
    /** @var ParsedClass  */
    protected $parsedClass;

    public function setUpTest(): void
    {
        $this->parsedClass = new ParsedClass("/**\n * @test\n @group group1\n*\\/", 'MyClass', 'My\\Name\\Space');
    }

    public function testHasAnnotationReturnsTrueWhenAnnotationPresent(): void
    {
        $hasAnnotation = $this->parsedClass->hasAnnotation('test');
        static::assertTrue($hasAnnotation);
    }

    public function testHasAnnotationReturnsFalseWhenAnnotationNotPresent(): void
    {
        $hasAnnotation = $this->parsedClass->hasAnnotation('pizza');
        static::assertFalse($hasAnnotation);
    }

    public function testHasAnnotationReturnsTrueWhenAnnotationAndValueMatch(): void
    {
        $hasAnnotation = $this->parsedClass->hasAnnotation('group', 'group1');
        static::assertTrue($hasAnnotation);
    }

    public function testHasAnnotationReturnsFalseWhenAnnotationAndValueDontMatch(): void
    {
        $hasAnnotation = $this->parsedClass->hasAnnotation('group', 'group2');
        static::assertFalse($hasAnnotation);
    }
}
