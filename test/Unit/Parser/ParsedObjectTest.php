<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Parser;

use ParaTest\Parser\ParsedClass;
use ParaTest\Tests\TestBase;

/**
 * @internal
 *
 * @covers \ParaTest\Parser\ParsedObject
 */
final class ParsedObjectTest extends TestBase
{
    /** @var ParsedClass  */
    private $parsedClass;

    public function setUpTest(): void
    {
        $this->parsedClass = new ParsedClass(self::class, [], 4);
    }

    public function testGetters(): void
    {
        static::assertSame(self::class, $this->parsedClass->getName());
    }
}
