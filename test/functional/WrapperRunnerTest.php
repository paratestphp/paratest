<?php

class WrapperRunnerTest extends FunctionalTestBase
{
    const TEST_METHODS_PER_CLASS = 5;
    const TEST_CLASSES = 6;

    public function testResultsAreCorrect()
    {
        $generator = new TestGenerator();
        $generator->generate(self::TEST_CLASSES, self::TEST_METHODS_PER_CLASS);

        $proc = $this->invokeParatest($generator->path, array(
            'runner' => 'WrapperRunner',
            'processes' => 3,
        ));

        $expected = self::TEST_CLASSES * self::TEST_METHODS_PER_CLASS;
        $this->assertTestsPassed($proc, $expected, $expected);
    }

    public function testMultiLineClassDeclarationWithFilenameDifferentThanClassnameIsSupported()
    {
        $this->assertTestsPassed($this->invokeParatest('special-classes', array(
            'runner' => 'WrapperRunner',
            'processes' => 3,
        )));
    }

    public function testRunningFewerTestsThanTheWorkersIsPossible()
    {
        $generator = new TestGenerator();
        $generator->generate(1, 1);

        $proc = $this->invokeParatest($generator->path, array(
            'runner' => 'WrapperRunner',
            'processes' => 2,
        ));

        $this->assertTestsPassed($proc, 1, 1);
    }

    public function testFatalErrorsAreReported()
    {
        if (PHP_VERSION_ID >= 70000) {
            $this->markTestSkipped('fatals are handled like normal exceptions with php7');
        }
        $proc = $this->invokeParatest('fatal-tests/UnitTestWithFatalFunctionErrorTest.php', array(
            'runner' => 'WrapperRunner',
            'processes' => 1,
        ));

        $errors = $proc->getErrorOutput();
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
        $proc = $this->invokeParatest('wrapper-runner-exit-code-tests/ErrorTest.php', $options);
        $output = $proc->getOutput();

        $this->assertContains('Tests: 1', $output);
        $this->assertContains('Failures: 0', $output);
        $this->assertContains('Errors: 1', $output);
        $this->assertEquals(2, $proc->getExitCode());

        $proc = $this->invokeParatest('wrapper-runner-exit-code-tests/FailureTest.php', $options);
        $output = $proc->getOutput();

        $this->assertContains('Tests: 1', $output);
        $this->assertContains('Failures: 1', $output);
        $this->assertContains('Errors: 0', $output);
        $this->assertEquals(1, $proc->getExitCode());

        $proc = $this->invokeParatest('wrapper-runner-exit-code-tests/SuccessTest.php', $options);
        $output = $proc->getOutput();

        $this->assertContains('OK (1 test, 1 assertion)', $output);
        $this->assertEquals(0, $proc->getExitCode());

        $options['processes'] = 3;
        $proc = $this->invokeParatest('wrapper-runner-exit-code-tests', $options);
        $output = $proc->getOutput();
        $this->assertContains('Tests: 3', $output);
        $this->assertContains('Failures: 1', $output);
        $this->assertContains('Errors: 1', $output);
        $this->assertEquals(2, $proc->getExitCode()); // There is at least one error so the exit code must be 2
    }
}
