<?php

declare(strict_types=1);

namespace ParaTest\Util;

use function array_splice;

final class ParatestFunction
{
    /**
     * @param array<int, string> $arg
     */
    public static function createScriptForParatest(array $arg, string $dir): string
    {
        unset($arg[1]);
        array_splice($arg, -3);
        $script          = $dir . '/paratest';
        $arg[]           = '--log-teamcity';
        $arg[]           = 'php://stdout';
        $_SERVER['argv'] = $arg;

        return $script;
    }
}
