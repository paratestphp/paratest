<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

/**
 * Specifically tests warnings in PHPUnit.
 */
final class PHPUnitWarningsTest extends FunctionalTestBase
{
    public function testTestsWithWarningsResultInFailure(): void
    {
        $proc = $this->invokeParatest(
            'warning-tests/HasWarningsTest.php',
            [
                'configuration' => FIXTURES . DS . 'warning-tests' . DS . 'phpunit.xml.dist',
            ]
        );

        $output = $proc->getOutput();

        static::assertStringContainsString('Warnings', $output, 'Test should output warnings');
        static::assertEquals(1, $proc->getExitCode(), 'Test suite should fail with 1');
    }
}
