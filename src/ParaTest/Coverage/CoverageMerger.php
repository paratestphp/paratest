<?php

namespace ParaTest\Coverage;

class CoverageMerger
{
    /**
     * @var \SebastianBergmann\CodeCoverage\CodeCoverage
     */
    private $coverage = null;

    public function addCoverage(\SebastianBergmann\CodeCoverage\CodeCoverage $coverage)
    {
        if ($this->coverage == null) {
            $this->setCoverage($coverage);
        } else {
            $this->mergeCoverage($coverage);
        }
    }

    /**
     * @param \SebastianBergmann\CodeCoverage\CodeCoverage $coverage
     */
    private function setCoverage(\SebastianBergmann\CodeCoverage\CodeCoverage $coverage)
    {
        $this->coverage = unserialize(serialize($coverage));
    }

    /**
     * @param \SebastianBergmann\CodeCoverage\CodeCoverage $coverage
     */
    private function mergeCoverage(\SebastianBergmann\CodeCoverage\CodeCoverage $coverage)
    {
        $this->coverage->merge($coverage);
    }

    /**
     * @return \SebastianBergmann\CodeCoverage\CodeCoverage
     */
    public function getCoverage()
    {
        return $this->coverage;
    }
}
