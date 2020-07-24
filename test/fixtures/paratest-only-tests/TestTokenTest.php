<?php

declare(strict_types=1);

class TestTokenTest extends PHPUnit\Framework\TestCase
{
    public function testThereIsAToken(): void
    {
        $token = getenv('TEST_TOKEN');
        $this->assertTrue($token !== false);
    }
}
