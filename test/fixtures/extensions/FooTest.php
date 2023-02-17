<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\extensions;

use PHPUnit\Framework\TestCase;

/** @internal */
final class FooTest extends TestCase
{
    public function testExtensionValue(): void
    {
        $this->assertSame('success', MyExtension::$value);
    }
}
