<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\wrapper_runner_exit_code_tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
final class ErrorTest extends TestCase
{
    public function testError(): void
    {
        throw new RuntimeException('Error here!');
    }
}
