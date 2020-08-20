<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\Worker\WrapperWorker;
use ParaTest\Runners\PHPUnit\WorkerCrashedException;
use ParaTest\Tests\TestBase;
use SimpleXMLElement;
use Symfony\Component\Console\Output\BufferedOutput;

use function count;
use function file_get_contents;

/**
 * @covers \ParaTest\Runners\PHPUnit\Worker\BaseWorker
 */
final class WorkerTest extends TestBase
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
        $testCmd = $this->getCommand('passing-tests' . DS . 'TestOfUnits.php', $testLog);
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
        $testCmd = $this->getCommand('passing-tests' . DS . 'TestOfUnits.php', $testLog);
        $worker  = new WrapperWorker($this->output);
        $worker->start($this->phpunitWrapper, $this->options, 1);
        $worker->execute($testCmd);
        $worker->waitForFinishedJob();

        $this->assertJUnitLogIsValid($testLog);
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testTellsWhenItsFree(): void
    {
        $testLog = TMP_DIR . DS . 'test.xml';
        $testCmd = $this->getCommand('passing-tests' . DS . 'TestOfUnits.php', $testLog);
        $worker  = new WrapperWorker($this->output);
        $worker->start($this->phpunitWrapper, $this->options, 1);
        static::assertTrue($worker->isFree());

        $worker->execute($testCmd);
        static::assertFalse($worker->isFree());

        $worker->waitForFinishedJob();
        static::assertTrue($worker->isFree());
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testTellsWhenItsStopped(): void
    {
        $worker = new WrapperWorker($this->output);
        static::assertFalse($worker->isRunning());

        $worker->start($this->phpunitWrapper, $this->options, 1);
        static::assertTrue($worker->isRunning());

        $worker->stop();
        $worker->waitForStop();
        static::assertFalse($worker->isRunning());
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testProcessIsMarkedAsCrashedWhenItFinishesWithNonZeroExitCode(): void
    {
        // fake state: process has already exited (with non-zero exit code) but worker did not yet notice
        $worker = new WrapperWorker($this->output);
        $worker->start('-r exit(255);', $this->createOptionsFromArgv([]), 1);
        $worker->waitForStop();

        static::expectException(WorkerCrashedException::class);

        $worker->checkNotCrashed();
    }

    public function testCanExecuteMultiplePHPUnitCommands(): void
    {
        $worker = new WrapperWorker($this->output);
        $worker->start($this->phpunitWrapper, $this->options, 1);

        $testLog = TMP_DIR . DS . 'test.xml';
        $testCmd = $this->getCommand('passing-tests' . DS . 'TestOfUnits.php', $testLog);
        $worker->execute($testCmd);

        $testLog2 = TMP_DIR . DS . 'test2.xml';
        $testCmd2 = $this->getCommand('failing-tests' . DS . 'UnitTestWithErrorTest.php', $testLog2);
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
