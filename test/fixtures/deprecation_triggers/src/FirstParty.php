<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\deprecation_triggers\src;

use ParaTest\Tests\fixtures\deprecation_triggers\third_party\ThirdParty;

use function trigger_error;

use const E_USER_DEPRECATED;

/** @internal */
final class FirstParty
{
    public static function selfDeprecation(): bool
    {
        return trigger_error('self deprecation', E_USER_DEPRECATED);
    }

    public static function directDeprecation(): bool
    {
        return ThirdParty::deprecated();
    }
}
