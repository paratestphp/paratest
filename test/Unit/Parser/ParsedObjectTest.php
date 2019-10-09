<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Parser;

use ParaTest\Parser\ParsedClass;

class ParsedObjectTest extends \ParaTest\Tests\TestBase
{
    protected $parsedClass;

    public function setUp(): void
    {
        $this->parsedClass = new ParsedClass("/**\n * @test\n @group group1\n*\/", 'MyClass', 'My\\Name\\Space');
    }

    public function testHasAnnotationReturnsTrueWhenAnnotationPresent()
    {
        $hasAnnotation = $this->parsedClass->hasAnnotation('test');
        $this->assertTrue($hasAnnotation);
    }

    public function testHasAnnotationReturnsFalseWhenAnnotationNotPresent()
    {
        $hasAnnotation = $this->parsedClass->hasAnnotation('pizza');
        $this->assertFalse($hasAnnotation);
    }

    public function testHasAnnotationReturnsTrueWhenAnnotationAndValueMatch()
    {
        $hasAnnotation = $this->parsedClass->hasAnnotation('group', 'group1');
        $this->assertTrue($hasAnnotation);
    }

    public function testHasAnnotationReturnsFalseWhenAnnotationAndValueDontMatch()
    {
        $hasAnnotation = $this->parsedClass->hasAnnotation('group', 'group2');
        $this->assertFalse($hasAnnotation);
    }
}
