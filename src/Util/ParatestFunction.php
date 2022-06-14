<?php

declare(strict_types=1);

namespace ParaTest\Util;

use RuntimeException;

use function count;

final class ParatestFunction
{
    /**
     * @param array<int, string> $arg
     */
    public static function createScriptForParatest(array $arg, string $dir): string
    {
        if (5 < count($arg)) {
            throw new RuntimeException('Do not use other options. Only the default ones are handled.');
        }

        $directory = $arg[0];
        unset($arg);
        $script          = $dir . '/paratest';
        $arg[]           = $directory;
        $arg[]           = '--log-teamcity';
        $arg[]           = 'php://stdout';
        $_SERVER['argv'] = $arg;

        return $script;
    }
}
