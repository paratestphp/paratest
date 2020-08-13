<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class Issue505TokensTest extends TestCase
{
    public function testHaveNoEnv(): void
    {
        self::assertSame('1', getenv('TEST_TOKEN'));
        self::assertStringStartsWith('1_', getenv('UNIQUE_TEST_TOKEN'));
        self::assertSame(15, strlen(getenv('UNIQUE_TEST_TOKEN')));
    }
}