<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\deprecation_triggers\third_party;

use function trigger_error;

use const E_USER_DEPRECATED;

/** @internal */
final class ThirdParty
{
    public static function deprecated(): bool
    {
        return trigger_error('third party deprecation', E_USER_DEPRECATED);
    }

    public static function indirectDeprecation(): bool
    {
        return self::deprecated();
    }
}
