<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\garbage_collection;

use PHPUnit\Framework\TestCase;

use function gc_enabled;

final class GarbageCollectionTest extends TestCase
{
    public function testGarbageCollectorIsControlledByPhpunit(): void
    {
        self::assertFalse(gc_enabled());
    }
}
