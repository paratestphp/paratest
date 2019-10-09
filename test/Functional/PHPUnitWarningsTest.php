<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

/**
 * Specifically tests warnings in PHPUnit.
 */
class PHPUnitWarningsTest extends FunctionalTestBase
{
    public function testTestsWithWarningsResultInFailure()
    {
        $proc = $this->invokeParatest(
            'warning-tests/HasWarningsTest.php',
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
