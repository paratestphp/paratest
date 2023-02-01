<?php

declare(strict_types=1);

namespace ParaTest\Coverage;

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData;
use SebastianBergmann\Environment\Runtime;

use function array_map;
use function array_slice;
use function assert;
use function filesize;
use function is_file;
use function unlink;

/** @internal */
final class CoverageMerger
{
    public function __construct(
        private readonly CodeCoverage $coverage
    ) {}

    public function addCoverageFromFile(string $coverageFile): void
    {
        if (! is_file($coverageFile) || filesize($coverageFile) === 0) {
            $extra = 'This means a PHPUnit process has crashed.';
            if (! (new Runtime())->canCollectCodeCoverage()) {
                // @codeCoverageIgnoreStart
                $extra = 'No coverage driver found! Enable one of Xdebug, PHPDBG or PCOV for coverage.';
                // @codeCoverageIgnoreEnd
            }

            throw new EmptyCoverageFileException("Coverage file {$coverageFile} is empty. " . $extra);
        }

        /** @psalm-suppress UnresolvableInclude **/
        $coverage = include $coverageFile;
        assert($coverage instanceof CodeCoverage);

        $this->coverage->merge($coverage);

//        unlink($coverageFile);
    }
}
