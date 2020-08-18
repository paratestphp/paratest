<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Parser;

use ParaTest\Parser\ParsedClass;
use ParaTest\Parser\ParsedFunction;
use ParaTest\Tests\TestBase;

/**
 * @covers \ParaTest\Parser\ParsedClass
 */
final class ParsedClassTest extends TestBase
{
    /** @var ParsedClass  */
    private $class;
    /** @var ParsedFunction[]  */
    private $methods;

    public function setUpTest(): void
    {
        $this->methods = [
            new ParsedFunction(
                '/**
              * @group group1
              */',
                'testFunction'
            ),
            new ParsedFunction(
                '/**
              * @group group2
              */',
                'testFunction2'
            ),
            new ParsedFunction('', 'testFunction3'),
        ];
        $this->class   = new ParsedClass('', 'MyTestClass', 'MyNamespace', $this->methods);
    }

    public function testGetNamespace(): void
    {
        static::assertSame('MyNamespace', $this->class->getNamespace());
    }

    public function testGetMethodsReturnsMethods(): void
    {
        static::assertSame($this->methods, $this->class->getMethods([]));
    }

    public function testGetMethodsMultipleAnnotationsReturnsMethods(): void
    {
        $goodMethod     = new ParsedFunction(
            '/**
              * @group group1
              */',
            'testFunction'
        );
        $goodMethod2    = new ParsedFunction(
            '/**
              * @group group2
              */',
            'testFunction2'
        );
        $badMethod      = new ParsedFunction(
            '/**
              * @group group3
              */',
            'testFunction2'
        );
        $annotatedClass = new ParsedClass('', 'MyTestClass', '', [$goodMethod, $goodMethod2, $badMethod]);
        $methods        = $annotatedClass->getMethods(['group1', 'group2']);
        static::assertSame([$goodMethod, $goodMethod2], $methods);
    }

    public function testGetMethodsExceptsAdditionalAnnotationFilter(): void
    {
        $group1 = $this->class->getMethods(['group1']);
        static::assertCount(1, $group1);
        static::assertSame($this->methods[0], $group1[0]);
    }

    public function testGetAllClassMethodsIfClassBelongsToGroup(): void
    {
        $class  = new ParsedClass('/** @group group9 */', 'MyTestClass', 'MyNamespace', $this->methods);
        $group1 = $class->getMethods(['group9']);
        static::assertCount(3, $group1);
        static::assertSame($this->methods[0], $group1[0]);
    }
}
