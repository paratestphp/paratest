<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH869;

use function trigger_error;

use const E_USER_DEPRECATED;
use const E_USER_NOTICE;

/** @internal */
final class Something
{
    public function raiseDeprecated(): bool
    {
        @trigger_error('what', E_USER_DEPRECATED);

        return true;
    }

    public function raiseNotice(): bool
    {
        @trigger_error('what', E_USER_NOTICE);

        return true;
    }
}
