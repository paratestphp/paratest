<?php

declare(strict_types=1);

final class UnitTestThatExitsLoudlyTest extends \PHPUnit\Framework\TestCase
{
    public function testExit(): void
    {
        exit(1);
    }
}
