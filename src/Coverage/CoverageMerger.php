<?php

declare(strict_types=1);

namespace ParaTest\Coverage;

use SebastianBergmann\CodeCoverage\CodeCoverage;

class CoverageMerger
{
    /**
     * @var CodeCoverage
     */
    private $coverage = null;

    /**
     * @param CodeCoverage $coverage
     */
    private function addCoverage(CodeCoverage $coverage)
    {
        if (null === $this->coverage) {
            $this->coverage = $coverage;
        } else {
            $this->coverage->merge($coverage);
        }
    }

    /**
     * Returns coverage object from file.
     *
     * @param \SplFileObject $coverageFile coverage file
     *
     * @return CodeCoverage
     */
    private function getCoverageObject(\SplFileObject $coverageFile)
    {
        if ('<?php' === $coverageFile->fread(5)) {
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
     * @throws \RuntimeException When coverage file is empty
     */
    public function addCoverageFromFile($coverageFile)
    {
        if ($coverageFile === null || !file_exists($coverageFile)) {
            return;
        }

        $file = new \SplFileObject($coverageFile);

        if (0 === $file->getSize()) {
            throw new \RuntimeException(
                "Coverage file {$file->getRealPath()} is empty. This means a PHPUnit process has crashed."
            );
        }

        $this->addCoverage($this->getCoverageObject($file));

        unlink($file->getRealPath());
    }

    /**
     * Get coverage report generator.
     *
     * @return CoverageReporterInterface
     */
    public function getReporter()
    {
        return new CoverageReporter($this->coverage);
    }
}
