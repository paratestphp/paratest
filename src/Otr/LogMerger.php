<?php

declare(strict_types=1);

namespace ParaTest\Otr;

use SplFileInfo;

/**
 * @internal
 *
 * @immutable
 */
final readonly class LogMerger
{
    /** @param list<SplFileInfo> $otrFiles */
    public function merge(array $otrFiles)
    {
        $mainSuite = null;
        foreach ($otrFiles as $otrFile) {
            if (! $otrFile->isFile()) {
                continue;
            }

            $otherSuite = Events::fromFile($otrFile);
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
