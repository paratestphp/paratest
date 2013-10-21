<?php

namespace ParaTest\Coverage;

class CoverageMerger
{
    /**
     * @var \PHP_CodeCoverage
     */
    private $coverage = null;

    public function addCoverage(\PHP_CodeCoverage $coverage)
    {
        if ($this->coverage == null) {
            $this->setCoverage($coverage);
        } else {
            $this->mergeCoverage($coverage);
        }
    }

    /**
     * @param \PHP_CodeCoverage $coverage
     */
    private function setCoverage(\PHP_CodeCoverage $coverage)
    {
        $this->coverage = unserialize(serialize($coverage));
    }

    /**
     * @param \PHP_CodeCoverage $coverage
     */
    private function mergeCoverage(\PHP_CodeCoverage $coverage)
    {
        $this->coverage->merge($coverage);
    }

    /**
     * @return \PHP_CodeCoverage
     */
    public function getCoverage()
    {
        return $this->coverage;
    }
}
