<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use Exception;
use PHPUnit;
use Symfony\Component\Process\Process;

class FunctionalTestBase extends PHPUnit\Framework\TestCase
{
    protected function fixture($fixture)
    {
        $fixture = FIXTURES . DS . $fixture;
        if (!file_exists($fixture)) {
            throw new Exception("Fixture $fixture not found");
        }

        return $fixture;
    }

    protected function invokeParatest($path, $options = [], $callback = null)
    {
        $invoker = new ParaTestInvoker($this->fixture($path), BOOTSTRAP);

        return $invoker->execute($options, $callback);
    }

    protected function assertTestsPassed(Process $proc, $testPattern = '\d+', $assertionPattern = '\d+')
    {
        $this->assertRegExp(
            "/OK \($testPattern tests?, $assertionPattern assertions?\)/",
            $proc->getOutput()
        );
        $this->assertEquals(0, $proc->getExitCode());
    }

    /**
     * Checks if the sqlite extension is loaded and skips the test if not.
     */
    protected function guardSqliteExtensionLoaded()
    {
        $sqliteExtension = 'pdo_sqlite';
        if (!extension_loaded($sqliteExtension)) {
            $this->markTestSkipped("Skipping test: Extension '$sqliteExtension' not found.");
        }
    }
}
