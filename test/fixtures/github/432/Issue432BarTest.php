<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @group group1
 */
class Issue432BarTest extends TestCase
{
    public function testTrue(): void
    {
        $this->assertTrue(true);
    }
}
