<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

final class ProcessCallback
{
    /** @var string */
    private $type;
    /** @var string */
    private $buffer;

    public function callback(string $type, string $buffer): void
    {
        $this->type   = $type;
        $this->buffer = $buffer;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }
}
