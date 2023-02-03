<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\common_results;

use PHPUnit\Framework\TestCase;

/** @internal */
final class WarningTest extends TestCase
{
    public function testWarning(): void
    {
        trigger_error('test', E_USER_WARNING);
        $this->assertTrue(true);
    }
}
