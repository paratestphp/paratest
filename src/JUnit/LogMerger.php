<?php

declare(strict_types=1);

namespace ParaTest\JUnit;

use SplFileInfo;

/**
 * @internal
 *
 * @immutable
 */
final readonly class LogMerger
{
    /** @param list<SplFileInfo> $junitFiles */
    public function merge(array $junitFiles): ?TestSuite
    {
        $mainSuite = null;
        foreach ($junitFiles as $junitFile) {
            if (! $junitFile->isFile()) {
                continue;
            }

            $otherSuite = TestSuite::fromFile($junitFile);
            if ($mainSuite === null) {
                $mainSuite = $otherSuite;
                continue;
            }

            if ($mainSuite->name !== $otherSuite->name) {
                if ($mainSuite->name !== '') {
                    $mainSuite = new TestSuite(
                        '',
                        $mainSuite->tests,
                        $mainSuite->assertions,
                        $mainSuite->failures,
                        $mainSuite->errors,
                        $mainSuite->skipped,
                        $mainSuite->time,
                        '',
                        [$mainSuite->name => $mainSuite],
                        [],
                    );
                }

                if ($otherSuite->name !== '') {
                    $otherSuite = new TestSuite(
                        '',
                        $otherSuite->tests,
                        $otherSuite->assertions,
                        $otherSuite->failures,
                        $otherSuite->errors,
                        $otherSuite->skipped,
                        $otherSuite->time,
                        '',
                        [$otherSuite->name => $otherSuite],
                        [],
                    );
                }
            }

            $mainSuite = $mainSuite->mergeWith($otherSuite);
        }

        return $mainSuite;
    }
}
