<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH1081;

use const E_USER_DEPRECATED;

/** @internal */
final class ClassWithDeprecation
{
    public function foo(): bool
    {
        custom_trigger('bar', E_USER_DEPRECATED);

        return true;
    }
}
