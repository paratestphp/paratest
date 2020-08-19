<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Parser;

use ParaTest\Parser\ParsedClass;
use ParaTest\Tests\TestBase;

/**
 * @covers \ParaTest\Parser\ParsedObject
 * @covers \ParaTest\Parser\ParsedFunction
 */
final class ParsedObjectTest extends TestBase
{
    /** @var ParsedClass  */
    private $parsedClass;
    /** @var string */
    private $docBlock;

    public function setUpTest(): void
    {
        $this->docBlock    = "/**\n * @test\n @group group1\n*\\/";
        $this->parsedClass = new ParsedClass($this->docBlock, self::class, 'My\\Name\\Space', []);
    }

    public function testGetters(): void
    {
        static::assertSame(self::class, $this->parsedClass->getName());
        static::assertSame($this->docBlock, $this->parsedClass->getDocBlock());
    }
}
