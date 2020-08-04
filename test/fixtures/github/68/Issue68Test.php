<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class Issue68Test extends TestCase
{
    public function testConfigAvailableInBootstrap(): void
    {
        $this->assertTrue($_ENV['configAvailableInBootstrap']);
    }
}
