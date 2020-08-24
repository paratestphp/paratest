<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\wrapper_runner_exit_code_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class FailureTest extends TestCase
{
    public function testFailure(): void
    {
        $this->assertTrue(false);
    }
}
