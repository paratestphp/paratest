<?php

declare(strict_types=1);

class FunctionalModeEachTestCalledOnce extends PHPUnit\Framework\TestCase
{
    public function testOne(): void
    {
        $this->assertTrue(true);
    }

    public function testOneIsNotAlone(): void
    {
        $this->assertNotEmpty('This test is to ensure that in functional mode tests are not executed multiple times #53');
    }
}
