<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\skipped_tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class IncompleteTest extends TestCase
{
    public function testIncomplete(): void
    {
        $this->markTestIncomplete();
    }
}
