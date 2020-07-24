<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ErrorTest extends TestCase
{
    public function testError(): void
    {
        throw new Exception('Error here!');
    }
}
