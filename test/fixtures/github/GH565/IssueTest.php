<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH565;

use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class IssueTest extends TestCase
{
    /**
     * @dataProvider provideIncomplete
     */
    public function testIncompleteByDataProvider(): void
    {
    }

    public function provideIncomplete(): void
    {
        $this->markTestIncomplete('foo');
    }

    /**
     * @dataProvider provideSkipped
     */
    public function testSkippedByDataProvider(): void
    {
    }

    public function provideSkipped(): void
    {
        $this->markTestSkipped('bar');
    }

    /**
     * @dataProvider provideError
     */
    public function testErrorByDataProvider(): void
    {
    }

    public function provideError(): void
    {
        throw new LogicException('baz');
    }
}
