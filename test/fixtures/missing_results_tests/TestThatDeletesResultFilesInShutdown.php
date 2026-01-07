<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\missing_results_tests;

use PHPUnit\Framework\TestCase;

use function file_exists;
use function register_shutdown_function;
use function unlink;
use function usleep;

/**
 * This test simulates the scenario where a worker process crashes after test execution
 * but before result files are properly written (e.g., due to OOM during clean-up).
 *
 * It registers a shutdown function that waits for files to be written, then deletes them,
 * simulating the case where application->end() is interrupted before completion.
 *
 * @internal
 */
final class TestThatDeletesResultFilesInShutdown extends TestCase
{
    public function testThatSucceedsButDeletesResultFiles(): void
    {
        register_shutdown_function(static function (): void {
            $testResultFile = null;
            $coverageFile   = null;

            /** @var array<int,string> $argv */
            $argv = $_SERVER['argv'] ?? [];

            foreach ($argv as $i => $arg) {
                if ($arg === '--test-result-file' && isset($argv[$i + 1])) {
                    $testResultFile = $argv[$i + 1];
                }

                if ($arg !== '--coverage-php' || ! isset($argv[$i + 1])) {
                    continue;
                }

                $coverageFile = $argv[$i + 1];
            }

            if ($testResultFile !== null) {
                $maxAttempts = 100;
                $attempt     = 0;
                while (! file_exists($testResultFile) && $attempt < $maxAttempts) {
                    usleep(10000); // 10ms
                    ++$attempt;
                }

                if (file_exists($testResultFile)) {
                    unlink($testResultFile);
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
