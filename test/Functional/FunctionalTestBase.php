<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use Exception;
use PHPUnit;
use Symfony\Component\Process\Process;

use function extension_loaded;
use function file_exists;

class FunctionalTestBase extends PHPUnit\Framework\TestCase
{
    protected function fixture(string $fixture): string
    {
        $fixture = FIXTURES . DS . $fixture;
        if (! file_exists($fixture)) {
            throw new Exception("Fixture $fixture not found");
        }

        return $fixture;
    }

    /**
     * @param array<int|string, string|int> $options
     *
     * @return Process<string>
     */
    protected function invokeParatest(string $path, array $options = [], ?callable $callback = null): Process
    {
        $invoker = new ParaTestInvoker($this->fixture($path), BOOTSTRAP);

        return $invoker->execute($options, $callback);
    }

    /**
     * @param Process<string> $proc
     */
    protected function assertTestsPassed(
        Process $proc,
        string $testPattern = '\d+',
        string $assertionPattern = '\d+'
    ): void {
        $this->assertMatchesRegularExpression(
            "/OK \($testPattern tests?, $assertionPattern assertions?\)/",
            $proc->getOutput()
        );
        $this->assertEquals(0, $proc->getExitCode());
    }

    /**
     * Checks if the sqlite extension is loaded and skips the test if not.
     */
    protected function guardSqliteExtensionLoaded(): void
    {
        $sqliteExtension = 'pdo_sqlite';
        if (extension_loaded($sqliteExtension)) {
            return;
        }

        $this->markTestSkipped("Skipping test: Extension '$sqliteExtension' not found.");
    }
}
