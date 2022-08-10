<?php

declare(strict_types=1);

namespace ParaTest\Util;

use function array_search;
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
            $coverage = array_search('-dpcov.enabled=1', $argv, true) ?: array_search('-dxdebug.mode=coverage', $argv, true);
            if ($coverage !== false) {
                // Unset the coverage option
                unset($argv[$coverage]);
            }

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
