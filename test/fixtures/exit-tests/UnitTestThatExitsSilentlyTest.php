<?php

declare(strict_types=1);

final class UnitTestThatExitsSilentlyTest extends \PHPUnit\Framework\TestCase
{
    public function testExit(): void
    {
        exit(0);
    }
}
