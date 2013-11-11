<?php

namespace ParaTest\Coverage;

class CoverageMergerTest extends \PHPUnit_Framework_TestCase
{
    public function testSimpleMerge()
    {
        $coverage1 = new \PHP_CodeCoverage();
        $coverage1->append(
            array(
                'someFile' => array(1 => 1),
                'someOtherFile' => array(1 => 1)
            ),
            'Test1'
        );
        $coverage2 = new \PHP_CodeCoverage();
        $coverage2->append(
            array(
                'someFile' => array(1 => 1, 2 => 1)
            ),
            'Test2'
        );

        $merger = new CoverageMerger();
        $merger->addCoverage($coverage1);
        $merger->addCoverage($coverage2);
        $coverage = $merger->getCoverage();

        $data = $coverage->getData();
        $this->assertEquals(2, count($data['someFile'][1]));
        $this->assertEquals('Test1', $data['someFile'][1][0]);
        $this->assertEquals('Test2', $data['someFile'][1][1]);

        $this->assertEquals(1, count($data['someFile'][2]));
        $this->assertEquals('Test2', $data['someFile'][2][0]);

        $this->assertEquals(1, count($data['someOtherFile'][1]));
        $this->assertEquals('Test1', $data['someOtherFile'][1][0]);
    }
}
