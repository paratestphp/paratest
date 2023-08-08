<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH431;

use PHPUnit\Framework\TestCase;

use function str_repeat;

/** @internal */
final class IssueTest extends TestCase
{
    public function testFillBuffers(): void
    {
        // the string is larger than the output buffer.
        // if the parent process doesn't read the output buffer, this test will hang forever.
        echo str_repeat('a', 10000);

        $this->assertTrue(true);
    }
}
