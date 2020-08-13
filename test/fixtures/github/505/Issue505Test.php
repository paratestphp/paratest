<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class Issue505Test extends TestCase
{
    public function testHaveNoEnv(): void
    {
        self::assertFalse(getenv('TEST_TOKEN'));
        self::assertFalse(getenv('UNIQUE_TEST_TOKEN'));
    }
}