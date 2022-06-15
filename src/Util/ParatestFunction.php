<?php

declare(strict_types=1);

namespace ParaTest\Util;

use function array_splice;

final class ParatestFunction
{
    /**
     * @param array<int, string> $argv
     */
    public static function handleArgvFromPhpstorm(array &$argv): string
    {
        unset($argv[1]);
        array_pop($argv);
        $argv[]  = '--log-teamcity';
        $argv[]  = 'php://stdout';
    }
}
