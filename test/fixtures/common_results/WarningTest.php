<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\common_results;

use PHPUnit\Framework\TestCase;

use function trigger_error;

use const E_USER_WARNING;

/** @internal */
final class WarningTest extends TestCase
{
    public function testWarning(): void
    {
        trigger_error('test', E_USER_WARNING);
        $this->assertTrue(true);
    }
}
