<?php

declare(strict_types=1);

namespace ParaTest\Util;

use RuntimeException;

use function array_search;
use function array_unshift;
use function in_array;
use function preg_match;
use function str_ends_with;
use function str_replace;
use function substr;

/** @internal */
final readonly class PhpstormHelper
{
    /**
     * @param array<int, string> $argv
     *
     * @param-out string $phpunitBinary
     */
    public static function handleArgvFromPhpstorm(
        array &$argv,
        string $paratestBinary,
        ?string &$phpunitBinary = null
    ): string {
        $phpunitKey    = self::getArgvKeyFor($argv, '/phpunit');
        $phpunitBinary = $argv[$phpunitKey];

        if (! in_array('--filter', $argv, true)) {
            $coverageArgKey = self::getCoverageArgvKey($argv);
            if ($coverageArgKey !== false) {
                unset($argv[$coverageArgKey]);
            }

            unset($argv[$phpunitKey]);

            return $paratestBinary;
        }

        unset($argv[self::getArgvKeyFor($argv, '/paratest_for_phpstorm')]);
        foreach ($argv as $index => $value) {
            if ($value === '--configuration' || $value === '--no-configuration' || $value === '--bootstrap') {
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
        $searchFor = str_replace('\\', '/', $searchFor);
        $searchFor = substr($searchFor, 1);

        foreach ($argv as $key => $arg) {
            $arg = str_replace('\\', '/', $arg);
            if (
                $arg === $searchFor
                || $arg === $searchFor . '.phar'
                || str_ends_with($arg, '/' . $searchFor)
                || str_ends_with($arg, '/' . $searchFor . '.phar')
                || ($searchFor === 'phpunit' && preg_match('~(?:^|/)phpunit-[0-9][^/]*\\.phar$~D', $arg) === 1)
            ) {
                return $key;
            }
        }

        throw new RuntimeException("Missing path to '$searchFor'");
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
