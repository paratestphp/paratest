<?php

declare(strict_types=1);

namespace ParaTest\Util;

use function array_unshift;
use function in_array;

/**
 * @internal
 */
final class PhpstormHelper
{
    /**
     * @param array<int, string> $argv
     */
    public static function handleArgvFromPhpstorm(array &$argv, string $paratestBinary): string
    {
        if (! in_array('--filter', $argv, true)) {
            unset($argv[1]);

            return $paratestBinary;
        }

        unset($argv[0]);
        $phpunitBinary = $argv[1];
        foreach ($argv as $index => $value) {
            if ($value === '--configuration' || $value === '--bootstrap') {
                break;
            }

            unset($argv[$index]);
        }

        array_unshift($argv, $phpunitBinary);

        return $phpunitBinary;
    }
}
