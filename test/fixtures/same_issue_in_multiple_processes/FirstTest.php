<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\same_issue_in_multiple_processes;

use PHPUnit\Framework\TestCase;

/** @internal */
final class FirstTest extends TestCase
{
    public function testIssues(): void
    {
        self::assertTrue((new ClassWithIssues())->foo());
    }
}
