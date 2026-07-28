<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\WrapperRunner;

use ParaTest\WrapperRunner\PendingTestQueue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** @internal */
#[CoversClass(PendingTestQueue::class)]
final class PendingTestQueueTest extends TestCase
{
    public function testPopReturnsTestsInOrder(): void
    {
        $queue = PendingTestQueue::fromTests(['a', 'b']);

        self::assertSame('a', $queue->dequeue());
        self::assertSame('b', $queue->dequeue());
        self::assertNull($queue->dequeue());
    }

    public function testCallingPopAgainReturnsNull(): void
    {
        $queue = PendingTestQueue::fromTests(['a']);

        self::assertSame('a', $queue->dequeue());
        self::assertNull($queue->dequeue());
        self::assertNull($queue->dequeue());
    }

    public function testHandlesEmpty(): void
    {
        $queue = PendingTestQueue::fromTests([]);

        self::assertNull($queue->dequeue());
    }
}
