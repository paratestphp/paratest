<?php

namespace ParaTest\Coverage;

use \SebastianBergmann\CodeCoverage\CodeCoverage as CodeCoverage;

class CoverageMerger
{
    /**
     * @var \CodeCoverage
     */
    private $coverage = null;

    public function addCoverage(\CodeCoverage $coverage)
    {
        if ($this->coverage == null) {
            $this->setCoverage($coverage);
        } else {
            $this->mergeCoverage($coverage);
        }
    }

    /**
     * @param \CodeCoverage $coverage
     */
    private function setCoverage(\CodeCoverage $coverage)
    {
        $this->coverage = unserialize(serialize($coverage));
    }

    /**
     * @param \CodeCoverage $coverage
     */
    private function mergeCoverage(\CodeCoverage $coverage)
    {
        $this->coverage->merge($coverage);
    }

    /**
     * @return \CodeCoverage
     */
    public function getCoverage()
    {
        return $this->coverage;
    }
}
