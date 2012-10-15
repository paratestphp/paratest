<?php namespace ParaTest\Parser;

class ParsedObjectTest extends \TestBase
{
    protected $parsedClass;

    public function setUp()
    {
        $this->parsedClass = new ParsedClass("/**\n * @test\n *\/", 'MyClass', 'My\\Name\\Space');
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
}