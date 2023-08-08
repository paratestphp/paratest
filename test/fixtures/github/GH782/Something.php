<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH782;

/** @internal */
final class Something
{
    public readonly bool $value;

    public function __construct(bool $value)
    {
        $this->value = $value;
    }
}
