<?php

declare(strict_types=1);

namespace ParaTest\Util;

use RuntimeException;

use function array_search;
use function array_unshift;
use function in_array;
use function strlen;
use function substr_compare;

/** @internal */
final class PhpstormHelper
{
    /** @param  array<int, string> $argv */
    public static function handleArgvFromPhpstorm(array &$argv, string $paratestBinary): string
    {
        $phpunitKey = self::getArgvKeyFor($argv, '/phpunit');

        if (! in_array('--filter', $argv, true)) {
            $coverageArgKey = self::getCoverageArgvKey($argv);
            if ($coverageArgKey !== false) {
                unset($argv[$coverageArgKey]);
            }

            unset($argv[$phpunitKey]);

            return $paratestBinary;
        }

        unset($argv[self::getArgvKeyFor($argv, '/paratest_for_phpstorm')]);
        $phpunitBinary = $argv[$phpunitKey];
        foreach ($argv as $index => $value) {
            if ($value === '--configuration' || $value === '--bootstrap') {
                break;
            }

            unset($argv[$index]);
        }

        array_unshift($argv, $phpunitBinary);

        return $phpunitBinary;
    }

    /** @param  array<int, string> $argv */
    private static function getArgvKeyFor(array $argv, string $searchFor): int
    {
        foreach ($argv as $key => $arg) {
            if (self::strEndsWith($arg, $searchFor)) {
                return $key;
            }
        }

        throw new RuntimeException("Missing path to '$searchFor'");
    }

    /**
     * Polyfill from PHP 8.0, drop when 7.4 support ends
     */
    public static function strEndsWith(string $haystack, string $needle): bool
    {
        if ($needle === '' || $needle === $haystack) {
            return true;
        }

        if ($haystack === '') {
            return false;
        }

        $needleLength = strlen($needle);

        return $needleLength <= strlen($haystack) && substr_compare($haystack, $needle, -$needleLength) === 0;
    }

    /**
     * @param  array<int, string> $argv
     *
     * @return int|false
     */
    private static function getCoverageArgvKey(array $argv)
    {
        $coverageOptions = [
            '-dpcov.enabled=1',
            '-dxdebug.mode=coverage',
        ];

        foreach ($coverageOptions as $coverageOption) {
            $key = array_search($coverageOption, $argv, true);
            if ($key !== false) {
                return $key;
            }
        }

        return false;
    }
}
