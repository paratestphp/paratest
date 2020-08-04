<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ErrorTest extends TestCase
{
    public function testError(): void
    {
        throw new Exception('Error here!');
    }
}
