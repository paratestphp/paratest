<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH869;

use PHPUnit\Framework\TestCase;

/** @internal */
final class IssueTest extends TestCase
{
    public function testUnsuppressedButBaselineDeprecated(): void
    {
        self::assertTrue((new Something())->raiseDeprecated());
    }

    public function testSuppressedButNotBaselineNotice(): void
    {
        self::assertTrue((new Something())->raiseNotice());
    }
}
