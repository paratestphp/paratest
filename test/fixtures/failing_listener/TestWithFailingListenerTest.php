<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\failing_listener;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TestWithFailingListenerTest extends TestCase
{
    public function testMe(): void
    {
        $this->assertTrue(true);
    }
}
