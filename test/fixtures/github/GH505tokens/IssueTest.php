<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH505tokens;

use PHPUnit\Framework\TestCase;

use function getenv;
use function strlen;

/** @internal */
final class IssueTest extends TestCase
{
    public function testHaveNoEnv(): void
    {
        self::assertSame('1', getenv('TEST_TOKEN'));
        self::assertStringStartsWith('1_', getenv('UNIQUE_TEST_TOKEN'));
        self::assertSame(15, strlen(getenv('UNIQUE_TEST_TOKEN')));
    }
}
