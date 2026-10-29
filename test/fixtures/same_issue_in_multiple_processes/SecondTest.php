<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\same_issue_in_multiple_processes;

use PHPUnit\Framework\TestCase;

/** @internal */
final class SecondTest extends TestCase
{
    public function testIssues(): void
    {
        (new ClassWithIssues())->foo();

        self::assertTrue((new ClassWithIssues())->foo());
    }
}
