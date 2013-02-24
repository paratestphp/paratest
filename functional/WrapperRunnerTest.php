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

    public function testRunningFewerTestsThanTheWorkersIsPossible()
    {
        $this->path = FIXTURES . DS . 'small-tests';
        $testClasses = 1;

        $this->createSmallTests($testClasses);
        $output = $this->getParaTestOutput(false, array(
                'runner' => 'WrapperRunner',
                'processes' => 2,
        ));
        $expected = $testClasses * self::TEST_METHODS_PER_CLASS;
        $this->assertContains("OK ($expected tests, $expected assertions)", $output);
    }
    
    public function testFatalErrorsAreReported()
    {
        $this->path = FIXTURES . DS . 'fatal-tests/UnitTestWithFatalFunctionErrorTest.php';
        $output = $this->getParaTestErrors(false, array(
                'runner' => 'WrapperRunner',
                'processes' => 1,
        ));
        $this->assertContains('This worker has crashed', $output);
    }
}
