<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Parser;

use ParaTest\Parser\ParsedClass;
use ParaTest\Parser\ParsedFunction;
use ParaTest\Tests\TestBase;

/**
 * @internal
 *
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
        $this->class   = new ParsedClass('', 'MyTestClass', 'MyNamespace', $this->methods, 4);
    }

    public function testGetters(): void
    {
        static::assertSame('MyNamespace', $this->class->getNamespace());
        static::assertSame($this->methods, $this->class->getMethods());
        static::assertSame(4, $this->class->getParentsCount());
    }
}
