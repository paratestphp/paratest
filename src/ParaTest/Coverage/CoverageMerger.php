<?php

namespace ParaTest\Coverage;

use SebastianBergmann\CodeCoverage\CodeCoverage;

class CoverageMerger
{
    /**
     * @var \PHP_CodeCoverage|CodeCoverage
     */
    private $coverage = null;

    /**
     * @param \PHP_CodeCoverage|CodeCoverage $coverage
     */
    private function addCoverage($coverage)
    {
        if ($this->coverage == null) {
            $this->setCoverage($coverage);
        } else {
            $this->mergeCoverage($coverage);
        }
    }

    /**
     * @param \PHP_CodeCoverage|CodeCoverage $coverage
     */
    private function setCoverage($coverage)
    {
        $this->coverage = unserialize(serialize($coverage));
    }

    /**
     * @param \PHP_CodeCoverage|CodeCoverage $coverage
     */
    private function mergeCoverage($coverage)
    {
        $this->coverage->merge($coverage);
    }

    /**
     * Returns coverage object from file.
     *
     * @param string $coverageFile Coverage file.
     *
     * @return \PHP_CodeCoverage|CodeCoverage
     */
    private function getCoverageObject($coverageFile)
    {
        $coverage = file_get_contents($coverageFile);

        if (substr($coverage, 0, 5) === '<?php') {
            return include $coverageFile;
        }

        // the PHPUnit 3.x and below
        return unserialize($coverage);
    }

    /**
     * Adds the coverage contained in $coverageFile and deletes the file afterwards
     * @param string $coverageFile
     * @throws \RuntimeException When coverage file is empty
     */
    public function addCoverageFromFile($coverageFile)
    {
        if ($coverageFile === null || !file_exists($coverageFile)) {
            return;
        }

        if (filesize($coverageFile) === 0) {
            throw new \RuntimeException("Coverage file $coverageFile is empty. This means a PHPUnit process has crashed.");
        }

        $this->addCoverage($this->getCoverageObject($coverageFile));
        unlink($coverageFile);
    }

    /**
     * Get coverage report generator
     *
     * @return CoverageReporterInterface
     */
    public function getReporter()
    {
        if ($this->coverage instanceof \PHP_CodeCoverage) {
            return new CoverageReporterLegacy($this->coverage);
        }

        return new CoverageReporter($this->coverage);
    }
}
