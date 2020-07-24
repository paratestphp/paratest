<?php

declare(strict_types=1);

class HasWarningsTest extends PHPUnit\Framework\TestCase
{
    public function testPassingTest(): void
    {
        $this->assertTrue(true);
    }

    private function testPrivateTest(): void
    {
        $this->assertTrue(true);
    }

    /**
     * @dataProvider llamas
     */
    private function testMissingDataProvider(): void
    {
        $this->assertTrue(true);
    }
}
