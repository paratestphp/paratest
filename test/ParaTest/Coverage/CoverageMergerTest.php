<?php

namespace ParaTest\Coverage;

class CoverageMergerTest extends \PHPUnit_Framework_TestCase
{
    public function testSimpleMerge()
    {
        $firstFile = PARATEST_ROOT . '/src/ParaTest/Logging/LogInterpreter.php';
        $secondFile = PARATEST_ROOT . '/src/ParaTest/Logging/MetaProvider.php';

        $coverage1 = new \PHP_CodeCoverage();
        $coverage1->append(
            array(
                $firstFile => array(35 => 1),
                $secondFile => array(34 => 1)
            ),
            'Test1'
        );
        $coverage2 = new \PHP_CodeCoverage();
        $coverage2->append(
            array(
                $firstFile => array(35 => 1, 36 => 1)
            ),
            'Test2'
        );

        $merger = new CoverageMerger();
        $merger->addCoverage($coverage1);
        $merger->addCoverage($coverage2);
        $coverage = $merger->getCoverage();

        $data = $coverage->getData();
        $this->assertEquals(2, count($data[$firstFile][35]));
        $this->assertEquals('Test1', $data[$firstFile][35][0]);
        $this->assertEquals('Test2', $data[$firstFile][35][1]);

        $this->assertEquals(1, count($data[$firstFile][36]));
        $this->assertEquals('Test2', $data[$firstFile][36][0]);

        $this->assertEquals(1, count($data[$secondFile][34]));
        $this->assertEquals('Test1', $data[$secondFile][34][0]);
    }
}
