<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use InvalidArgumentException;
use PHPUnit;

use function file_exists;
use function sprintf;

abstract class FunctionalTestBase extends PHPUnit\Framework\TestCase
{
    final protected function fixture(string $fixture): string
    {
        $fixture = FIXTURES . DS . $fixture;
        if (! file_exists($fixture)) {
            throw new InvalidArgumentException("Fixture {$fixture} not found");
        }

        return $fixture;
    }

    final protected function assertTestsPassed(
        RunnerResult $proc,
        ?string $testPattern = null,
        ?string $assertionPattern = null
    ): void {
        static::assertMatchesRegularExpression(
            sprintf(
                '/OK \(%s tests?, %s assertions?\)/',
                $testPattern ?? '\d+',
                $assertionPattern ?? '\d+'
            ),
            $proc->getOutput(),
        );
        static::assertEquals(0, $proc->getExitCode());
    }

    /**
     * @param array<string, string|bool|int> $options
     */
    final protected function invokeParatest(
        ?string $path,
        array $options = [],
        ?string $cwd = null
    ): RunnerResult {
        if ($path !== null) {
            $path = $this->fixture($path);
        }

        $invoker = new ParaTestInvoker($path);

        return $invoker->execute($options, $cwd);
    }
}
