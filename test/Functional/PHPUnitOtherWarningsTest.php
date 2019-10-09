<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

/**
 * Test special PHPUnit Warnings processing.
 *
 * PHPUnit deprecated method calls, mocking non-existent methods and some other cases produce warnings that are output
 * slightly different. Now the paratest doesn't parse the output directly but relies on the JUnit XML logs.
 * This test checks whether the parates recognizes those warnings.
 */
class PHPUnitOtherWarningsTest extends FunctionalTestBase
{
    public function testTestsWithWarningsResultInFailure()
    {
        $proc = $this->invokeParatest(
            'warning-tests/HasOtherWarningsTest.php',
            [
                'bootstrap' => BOOTSTRAP,
                'configuration' => FIXTURES . DS . 'warning-tests' . DS . 'phpunit.xml.dist',
            ]
        );

        $output = $proc->getOutput();

        $this->assertStringContainsString('Warnings', $output, 'Test should output warnings');
        $this->assertEquals(1, $proc->getExitCode(), 'Test suite should fail with 1');
    }
}
