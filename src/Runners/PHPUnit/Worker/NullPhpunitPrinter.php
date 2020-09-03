<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\TestResult;
use PHPUnit\TextUI\ResultPrinter;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class NullPhpunitPrinter implements ResultPrinter
{
    use TestListenerDefaultImplementation;

    public function printResult(TestResult $result): void
    {
    }

    public function write(string $buffer): void
    {
    }
}
