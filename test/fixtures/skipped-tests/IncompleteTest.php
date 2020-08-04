<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class IncompleteTest extends TestCase
{
    public function testIncomplete(): void
    {
        $this->markTestIncomplete();
    }
}
