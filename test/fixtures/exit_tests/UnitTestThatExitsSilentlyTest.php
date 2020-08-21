<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\exit_tests;

use PHPUnit\Framework\TestCase;

final class UnitTestThatExitsSilentlyTest extends TestCase
{
    public function testExit(): void
    {
        exit(0);
    }
}
