<?php

declare(strict_types=1);

namespace ParaTest\Tests;

use PHPUnit\TextUI\Output\Printer;

/** @internal */
final class MemoryPrinter implements Printer
{
    private string $memory = '';
    private bool $flushed  = false;

    public function print(string $buffer): void
    {
        $this->memory .= $buffer;
    }

    public function tail(): string
    {
        $memory       = $this->memory;
        $this->memory = '';

        return $memory;
    }

    public function flush(): void
    {
        $this->flushed = true;
    }

    public function hasBeenFlushed(): bool
    {
        return $this->flushed;
    }
}
