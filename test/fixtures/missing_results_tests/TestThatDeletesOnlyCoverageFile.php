<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\missing_results_tests;

use PHPUnit\Framework\TestCase;

use function file_exists;
use function register_shutdown_function;
use function unlink;
use function unserialize;
use function usleep;

/**
 * This test simulates the scenario where the test result file is written successfully,
 * but the coverage file is missing (e.g., OOM during coverage file generation).
 *
 * @internal
 */
final class TestThatDeletesOnlyCoverageFile extends TestCase
{
    public function testThatSucceedsButDeletesCoverageFile(): void
    {
        register_shutdown_function(static function (): void {
            $coverageFile = null;

            /** @var array<int,string> $argv */
            $argv = $_SERVER['argv'] ?? [];

            foreach ($argv as $i => $arg) {
                if ($arg !== '--phpunit-argv' || ! isset($argv[$i + 1])) {
                    continue;
                }

                /** @var array<int,string> $phpunitArgv */
                $phpunitArgv = unserialize($argv[$i + 1]);
                foreach ($phpunitArgv as $j => $phpunitArg) {
                    if ($phpunitArg === '--coverage-php' && isset($phpunitArgv[$j + 1])) {
                        $coverageFile = $phpunitArgv[$j + 1];
                        break 2;
                    }
                }
            }

            if ($coverageFile === null) {
                return;
            }

            $maxAttempts = 100;
            $attempt     = 0;
            while (! file_exists($coverageFile) && $attempt < $maxAttempts) {
                usleep(10000); // 10ms
                ++$attempt;
            }

            if (! file_exists($coverageFile)) {
                return;
            }

            unlink($coverageFile);
        });

        self::assertTrue(true);
    }
}
