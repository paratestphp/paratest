<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\depends_intra_class;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/** @internal */
abstract class DependsTestCase extends TestCase
{
    public function testProducer(): string
    {
        self::assertTrue(true);

        return 'parent';
    }

    #[Depends('testProducer')]
    public function testConsumer(string $value): void
    {
        self::assertSame('parent', $value);
    }
}
