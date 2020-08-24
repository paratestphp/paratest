<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit\Worker;

use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\Worker\WrapperWorker;
use ParaTest\Tests\TestBase;
use SimpleXMLElement;
use Symfony\Component\Console\Output\BufferedOutput;

use function count;
use function file_get_contents;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\Worker\BaseWorker
 */
final class BaseWorkerTest extends TestBase
{
    /** @var string */
    private $bootstrap;
    /** @var string */
    private $phpunitWrapper;
    /** @var BufferedOutput */
    private $output;
    /** @var Options */
    private $options;

    public function setUpTest(): void
    {
        $this->bootstrap      = PARATEST_ROOT . DS . 'test' . DS . 'bootstrap.php';
        $this->phpunitWrapper = PARATEST_ROOT . DS . 'bin' . DS . 'phpunit-wrapper.php';
        $this->output         = new BufferedOutput();
        $this->options        = $this->createOptionsFromArgv([]);
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testReadsAPHPUnitCommandFromStdInAndExecutesItItsOwnProcess(): void
    {
        $testLog = TMP_DIR . DS . 'test.xml';
        $testCmd = $this->getCommand('passing_tests' . DS . 'TestOfUnits.php', $testLog);
        $worker  = new WrapperWorker($this->output);
        $worker->start($this->phpunitWrapper, $this->options, 1);
        $worker->execute($testCmd);

        $worker->stop();
        $worker->waitForStop();

        $this->assertJUnitLogIsValid($testLog);
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testKnowsWhenAJobIsFinished(): void
    {
        $testLog = TMP_DIR . DS . 'test.xml';
        $testCmd = $this->getCommand('passing_tests' . DS . 'TestOfUnits.php', $testLog);
        $worker  = new WrapperWorker($this->output);
        $worker->start($this->phpunitWrapper, $this->options, 1);
        $worker->execute($testCmd);
        $worker->waitForFinishedJob();

        $this->assertJUnitLogIsValid($testLog);
    }

    public function testCanExecuteMultiplePHPUnitCommands(): void
    {
        $worker = new WrapperWorker($this->output);
        $worker->start($this->phpunitWrapper, $this->options, 1);

        $testLog = TMP_DIR . DS . 'test.xml';
        $testCmd = $this->getCommand('passing_tests' . DS . 'TestOfUnits.php', $testLog);
        $worker->execute($testCmd);

        $testLog2 = TMP_DIR . DS . 'test2.xml';
        $testCmd2 = $this->getCommand('failing_tests' . DS . 'UnitTestWithErrorTest.php', $testLog2);
        $worker->execute($testCmd2);

        $worker->stop();
        $worker->waitForStop();

        $this->assertJUnitLogIsValid($testLog);
        $this->assertJUnitLogIsValid($testLog2);
    }

    /**
     * @return string[]
     */
    private function getCommand(string $testFile, string $logFile): array
    {
        return [
            PHPUNIT,
            '--bootstrap',
            $this->bootstrap,
            '--log-junit',
            $logFile,
            $this->fixture($testFile),
        ];
    }

    private function assertJUnitLogIsValid(string $logFile): void
    {
        static::assertFileExists($logFile);
        $log   = new SimpleXMLElement((string) file_get_contents($logFile));
        $count = count($log->testsuite->testcase);
        static::assertGreaterThan(1, $count, 'Not even a test has been executed');
    }
}
