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

        self::assertSame('a', $queue->dequeue(1));
        self::assertSame('b', $queue->dequeue(1));
        self::assertNull($queue->dequeue(1));
    }

    public function testCallingPopAgainReturnsNull(): void
    {
        $queue = PendingTestQueue::fromTests(['a']);

        self::assertSame('a', $queue->dequeue(1));
        self::assertNull($queue->dequeue(1));
        self::assertNull($queue->dequeue(1));
    }

    public function testHandlesEmpty(): void
    {
        $queue = PendingTestQueue::fromTests([]);

        self::assertNull($queue->dequeue(1));
    }

    public function testDifferentWorkerSkipsAlreadyAssignedAffinity(): void
    {
        $queue = new PendingTestQueue([
            'a' => ['a', 'aa'],
            'b' => ['b'],
        ]);

        self::assertSame('a', $queue->dequeue(1));
        self::assertSame('b', $queue->dequeue(2));
    }

    public function testWorkersWithoutAssignmentJoinOtherAffinitiesInOrder(): void
    {
        $queue = new PendingTestQueue([
            'a' => ['a', 'aa', 'aaa'],
            'b' => ['b', 'bb'],
        ]);

        self::assertSame('a', $queue->dequeue(1));
        self::assertSame('b', $queue->dequeue(2));
        self::assertSame('aa', $queue->dequeue(3));
        self::assertSame('bb', $queue->dequeue(4));
    }

    public function testWorkerJoinsOtherAffinitiesWhenOwnAssignmentIsDone(): void
    {
        $queue = new PendingTestQueue([
            'a' => ['a', 'aa'],
            'b' => ['b', 'bb'],
            'c' => ['c'],
        ]);

        self::assertSame('a', $queue->dequeue(1));
        self::assertSame('b', $queue->dequeue(2));
        self::assertSame('c', $queue->dequeue(3));
        self::assertSame('aa', $queue->dequeue(3));
        self::assertSame('bb', $queue->dequeue(3));
    }

    public function testHandlesNumericStringAffinity(): void
    {
        $queue = new PendingTestQueue([
            '123' => ['a', 'aa'],
        ]);

        self::assertSame('a', $queue->dequeue(1));
        self::assertSame('aa', $queue->dequeue(1));
        self::assertNull($queue->dequeue(1));
    }
}
