<?php

declare(strict_types=1);

namespace ParaTest\Coverage;

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\ProcessedCodeCoverageData;
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
        private ?CodeCoverage $coverage = null,
        private readonly int $testLimit = 0,
    ) {
    }

    private function addCoverage(CodeCoverage $coverage): void
    {
        if ($this->coverage === null) {
            $this->coverage = $coverage;
        } else {
            $this->coverage->merge($coverage);
        }

        $this->limitCoverageTests($this->coverage);
    }

    public function addCoverageFromFile(string $coverageFile): void
    {
        if (! is_file($coverageFile) || filesize($coverageFile) === 0) {
            $extra = 'This means a PHPUnit process has crashed.';
            if (! (new Runtime())->canCollectCodeCoverage()) {
                // @codeCoverageIgnoreStart
                $extra = 'No coverage driver found! Enable of of Xdebug, PHPDBG or PCOV for coverage.';
                // @codeCoverageIgnoreEnd
            }

            throw new EmptyCoverageFileException("Coverage file {$coverageFile} is empty. " . $extra);
        }

        /** @psalm-suppress UnresolvableInclude */
        $coverage = include $coverageFile;
        assert($coverage instanceof CodeCoverage);
        $this->addCoverage($coverage);

        unlink($coverageFile);
    }

    public function getCodeCoverageObject(): ?CodeCoverage
    {
        return $this->coverage;
    }

    private function limitCoverageTests(CodeCoverage $coverage): void
    {
        if ($this->testLimit === 0) {
            return;
        }

        $testLimit     = $this->testLimit;
        $data          = $coverage->getData(raw: true);
        $newData       = array_map(
            static fn (array $lines): array => array_map(
                static fn (array $value): array => array_slice($value, 0, $testLimit),
                $lines,
            ),
            $data->lineCoverage(),
        );
        $processedData = new ProcessedCodeCoverageData();
        $processedData->setLineCoverage($newData);

        $coverage->setData($processedData);
    }
}
