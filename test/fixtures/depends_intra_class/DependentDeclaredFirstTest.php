<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\depends_intra_class;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/** @internal */
final class DependentDeclaredFirstTest extends TestCase
{
    #[Depends('testProducer')]
    public function testConsumer(string $value): void
    {
        self::assertSame('ok', $value);
    }

    public function testProducer(): string
    {
        self::assertTrue(true);

        return 'ok';
    }
}
