<?php

declare(strict_types=1);

namespace ParaTest\Coverage;

use RuntimeException;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SplFileObject;

use function array_map;
use function array_slice;
use function extension_loaded;
use function file_exists;
use function function_exists;
use function ini_get;
use function is_array;
use function unlink;
use function unserialize;

use const PHP_SAPI;

class CoverageMerger
{
    /** @var CodeCoverage */
    private $coverage = null;
    /** @var int */
    private $test_limit;

    public function __construct(int $test_limit = 0)
    {
        $this->test_limit = $test_limit;
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

    /**
     * Returns coverage object from file.
     *
     * @param SplFileObject $coverageFile coverage file
     */
    private function getCoverageObject(SplFileObject $coverageFile): CodeCoverage
    {
        if ($coverageFile->fread(5) === '<?php') {
            return include $coverageFile->getRealPath();
        }

        $coverageFile->fseek(0);

        // the PHPUnit 3.x and below
        return unserialize($coverageFile->fread($coverageFile->getSize()));
    }

    /**
     * Adds the coverage contained in $coverageFile and deletes the file afterwards.
     *
     * @param string $coverageFile Code coverage file
     *
     * @throws RuntimeException When coverage file is empty.
     */
    public function addCoverageFromFile(?string $coverageFile = null): void
    {
        if ($coverageFile === null || ! file_exists($coverageFile)) {
            return;
        }

        $file = new SplFileObject($coverageFile);

        if ($file->getSize() === 0) {
            $extra = 'This means a PHPUnit process has crashed.';

            $xdebug = function_exists('xdebug_get_code_coverage');
            $phpdbg = PHP_SAPI === 'phpdbg';
            $pcov   = extension_loaded('pcov') && ini_get('pcov.enabled');

            if (! $xdebug && ! $phpdbg && ! $pcov) {
                $extra = 'No coverage driver found! Enable one of Xdebug, PHPDBG or PCOV for coverage.';
            }

            throw new RuntimeException(
                "Coverage file {$file->getRealPath()} is empty. " . $extra
            );
        }

        $this->addCoverage($this->getCoverageObject($file));

        unlink($file->getRealPath());
    }

    /**
     * Get coverage report generator.
     */
    public function getReporter(): CoverageReporterInterface
    {
        return new CoverageReporter($this->coverage);
    }

    /**
     * Get CodeCoverage object.
     */
    public function getCodeCoverageObject(): ?CodeCoverage
    {
        return $this->coverage;
    }

    private function limitCoverageTests(CodeCoverage $coverage): void
    {
        if (! $this->test_limit) {
            return;
        }

        $coverage->setData(array_map(
            function (array $lines) {
                return array_map(function ($value) {
                    if (! is_array($value)) {
                        return $value;
                    }

                    return array_slice($value, 0, $this->test_limit);
                }, $lines);
            },
            $coverage->getData($raw = true)
        ));
    }
}
