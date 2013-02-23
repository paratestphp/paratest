<?php

class WrapperRunnerTest extends FunctionalTestBase
{
    const TEST_METHODS_PER_CLASS = 5;

    public function setUp()
    {
        parent::setUp();
        $this->deleteSmallTests();
    }

    public function testResultsAreCorrect()
    {
        $this->path = FIXTURES . DS . 'small-tests';
        $testClasses = 6;

        $this->createSmallTests($testClasses);
        $output = $this->getParaTestOutput(false, array(
                'runner' => 'WrapperRunner',
                'processes' => 3,
        ));
        $expected = $testClasses * self::TEST_METHODS_PER_CLASS;
        $this->assertContains("OK ($expected tests, $expected assertions)", $output);
    }
}
