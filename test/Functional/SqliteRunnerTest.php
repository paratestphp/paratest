<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use ParaTest\Runners\PHPUnit\SqliteRunner;

/**
 * @coversNothing
 */
final class SqliteRunnerTest extends FunctionalTestBase
{
    private const TEST_METHODS_PER_CLASS = 5;

    private const TEST_CLASSES = 6;

    protected function setUp(): void
    {
        $this->guardSqliteExtensionLoaded();
        parent::setUp();
    }

    public function testResultsAreCorrect(): void
    {
        $generator = new TestGenerator();
        $generator->generate(self::TEST_CLASSES, self::TEST_METHODS_PER_CLASS);

        $proc = $this->invokeParatest($generator->path, ['--processes' => 3, '--runner' => SqliteRunner::class]);

        $expected = self::TEST_CLASSES * self::TEST_METHODS_PER_CLASS;
        $this->assertTestsPassed($proc, (string) $expected, (string) $expected);
    }

    public function testRunningFewerTestsThanTheWorkersIsPossible(): void
    {
        $generator = new TestGenerator();
        $generator->generate(1, 1);

        $proc = $this->invokeParatest($generator->path, ['--processes' => 2, '--runner' => SqliteRunner::class]);

        $this->assertTestsPassed($proc, '1', '1');
    }

    public function testExitCodes(): void
    {
        $options = ['--processes' => 1, '--runner' => SqliteRunner::class];
        $proc    = $this->invokeParatest(
            'wrapper-runner-exit-code-tests/ErrorTest.php',
            $options
        );
        $output  = $proc->getOutput();

        static::assertStringContainsString('Tests: 1', $output);
        static::assertStringContainsString('Failures: 0', $output);
        static::assertStringContainsString('Errors: 1', $output);
        static::assertEquals(2, $proc->getExitCode());

        $proc   = $this->invokeParatest(
            'wrapper-runner-exit-code-tests/FailureTest.php',
            $options
        );
        $output = $proc->getOutput();

        static::assertStringContainsString('Tests: 1', $output);
        static::assertStringContainsString('Failures: 1', $output);
        static::assertStringContainsString('Errors: 0', $output);
        static::assertEquals(1, $proc->getExitCode());

        $proc   = $this->invokeParatest(
            'wrapper-runner-exit-code-tests/SuccessTest.php',
            $options
        );
        $output = $proc->getOutput();

        static::assertStringContainsString('OK (1 test, 1 assertion)', $output);
        static::assertEquals(0, $proc->getExitCode());

        $options['--processes'] = 3;
        $proc                   = $this->invokeParatest(
            'wrapper-runner-exit-code-tests',
            $options
        );
        $output                 = $proc->getOutput();
        static::assertStringContainsString('Tests: 3', $output);
        static::assertStringContainsString('Failures: 1', $output);
        static::assertStringContainsString('Errors: 1', $output);
        static::assertEquals(2, $proc->getExitCode()); // There is at least one error so the exit code must be 2
    }
}
