<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH505;

use PHPUnit\Framework\TestCase;

use function getenv;

/** @internal */
final class IssueTest extends TestCase
{
    public function testHaveNoEnv(): void
    {
        self::assertFalse(getenv('TEST_TOKEN'));
        self::assertFalse(getenv('UNIQUE_TEST_TOKEN'));
    }
}
