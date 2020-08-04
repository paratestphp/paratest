<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use ParaTest\Runners\PHPUnit\WrapperRunner;

use function glob;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function unlink;

/**
 * @requires OSFAMILY Linux
 */
final class WrapperRunnerTest extends FunctionalTestBase
{
    private const TEST_METHODS_PER_CLASS = 5;

    private const TEST_CLASSES = 6;

    public function testResultsAreCorrect(): void
    {
        $generator = new TestGenerator();
        $generator->generate(self::TEST_CLASSES, self::TEST_METHODS_PER_CLASS);

        $proc = $this->invokeParatest($generator->path, ['processes' => 3], WrapperRunner::class);

        $expected = self::TEST_CLASSES * self::TEST_METHODS_PER_CLASS;
        $this->assertTestsPassed($proc, (string) $expected, (string) $expected);
    }

    public function testRunningFewerTestsThanTheWorkersIsPossible(): void
    {
        $generator = new TestGenerator();
        $generator->generate(1, 1);

        $proc = $this->invokeParatest($generator->path, ['processes' => 2], WrapperRunner::class);

        $this->assertTestsPassed($proc, '1', '1');
    }

    public function testExitCodes(): void
    {
        $options = ['processes' => 1];
        $proc    = $this->invokeParatest(
            'wrapper-runner-exit-code-tests/ErrorTest.php',
            $options,
            WrapperRunner::class
        );
        $output  = $proc->getOutput();

        static::assertStringContainsString('Tests: 1', $output);
        static::assertStringContainsString('Failures: 0', $output);
        static::assertStringContainsString('Errors: 1', $output);
        static::assertEquals(2, $proc->getExitCode());

        $proc   = $this->invokeParatest(
            'wrapper-runner-exit-code-tests/FailureTest.php',
            $options,
            WrapperRunner::class
        );
        $output = $proc->getOutput();

        static::assertStringContainsString('Tests: 1', $output);
        static::assertStringContainsString('Failures: 1', $output);
        static::assertStringContainsString('Errors: 0', $output);
        static::assertEquals(1, $proc->getExitCode());

        $proc   = $this->invokeParatest(
            'wrapper-runner-exit-code-tests/SuccessTest.php',
            $options,
            WrapperRunner::class
        );
        $output = $proc->getOutput();

        static::assertStringContainsString('OK (1 test, 1 assertion)', $output);
        static::assertEquals(0, $proc->getExitCode());

        $options['processes'] = 3;
        $proc                 = $this->invokeParatest(
            'wrapper-runner-exit-code-tests',
            $options,
            WrapperRunner::class
        );
        $output               = $proc->getOutput();
        static::assertStringContainsString('Tests: 3', $output);
        static::assertStringContainsString('Failures: 1', $output);
        static::assertStringContainsString('Errors: 1', $output);
        static::assertEquals(2, $proc->getExitCode()); // There is at least one error so the exit code must be 2
    }

    public function testParallelSuiteOption(): void
    {
        $testDir = sys_get_temp_dir() . DS . 'parallel-suite';
        if (! is_dir($testDir)) {
            mkdir($testDir);
        }

        foreach (glob($testDir . DS . '*') as $file) {
            unlink($file);
        }

        $proc = $this->invokeParatest(
            null,
            [
                'configuration' => $this->fixture('phpunit-parallel-suite.xml'),
                'parallel-suite' => true,
                'processes' => 2,
            ],
            WrapperRunner::class
        );

        $this->assertTestsPassed($proc);
    }
}
