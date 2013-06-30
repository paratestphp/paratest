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
        $this->getParaTestOutput(false, array(
                'runner' => 'WrapperRunner',
                'processes' => 1,
        ));
        $errors = $this->getErrorOutput();
        $this->assertContains('This worker has crashed', $errors);
    }

    public function functionalModeEnabledDataProvider()
    {
        return array(array(false));
    }
    /**
     * @dataProvider functionalModeEnabledDataProvider
     */
    public function testExitCodes($functionalModeEnabled)
    {
        $options = array(
            'runner' => 'WrapperRunner',
            'processes' => 1,
        );

        $this->path = FIXTURES . DS . 'wrapper-runner-exit-code-tests' . DS . 'ErrorTest.php';
        $output = $this->getParaTestOutput($functionalModeEnabled, $options);
        $this->assertContains('Tests: 1', $output);
        $this->assertContains('Failures: 0', $output);
        $this->assertContains('Errors: 1', $output);
        $this->assertEquals(2, $this->getExitCode());

        $this->path = FIXTURES . DS . 'wrapper-runner-exit-code-tests' . DS . 'FailureTest.php';
        $output = $this->getParaTestOutput($functionalModeEnabled, $options);
        $this->assertContains('Tests: 1', $output);
        $this->assertContains('Failures: 1', $output);
        $this->assertContains('Errors: 0', $output);
        $this->assertEquals(1, $this->getExitCode());

        $this->path = FIXTURES . DS . 'wrapper-runner-exit-code-tests' . DS . 'SuccessTest.php';
        $output = $this->getParaTestOutput($functionalModeEnabled, $options);
        $this->assertContains('OK (1 test, 1 assertion)', $output);
        $this->assertEquals(0, $this->getExitCode());

        $options['processes'] = 3;
        $this->path = FIXTURES . DS . 'wrapper-runner-exit-code-tests';
        $output = $this->getParaTestOutput($functionalModeEnabled, $options);
        $this->assertContains('Tests: 3', $output);
        $this->assertContains('Failures: 1', $output);
        $this->assertContains('Errors: 1', $output);
        $this->assertEquals(2, $this->getExitCode()); // There is at least one error so the exit code must be 2
    }
}
