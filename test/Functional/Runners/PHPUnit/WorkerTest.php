<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Worker\WrapperWorker;
use SimpleXMLElement;

class WorkerTest extends \ParaTest\Tests\TestBase
{
    protected static $descriptorspec = [
       0 => ['pipe', 'r'],
       1 => ['pipe', 'w'],
       2 => ['pipe', 'w'],
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->bootstrap = PARATEST_ROOT . '/test/bootstrap.php';
        $this->phpunitWrapper = PARATEST_ROOT . '/bin/phpunit-wrapper';
    }

    public function tearDown(): void
    {
        $this->deleteIfExists('/tmp/test.xml');
        $this->deleteIfExists('/tmp/test2.xml');
    }

    private function deleteIfExists($file)
    {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function testReadsAPHPUnitCommandFromStdInAndExecutesItItsOwnProcess()
    {
        $testLog = '/tmp/test.xml';
        $testCmd = $this->getCommand('passing-tests/TestOfUnits.php', $testLog);
        $worker = new WrapperWorker();
        $worker->start($this->phpunitWrapper);
        $worker->execute($testCmd);

        $worker->stop();
        $worker->waitForStop();

        $this->assertJUnitLogIsValid($testLog);
    }

    public function testKnowsWhenAJobIsFinished()
    {
        $testLog = '/tmp/test.xml';
        $testCmd = $this->getCommand('passing-tests/TestOfUnits.php', $testLog);
        $worker = new WrapperWorker();
        $worker->start($this->phpunitWrapper);
        $worker->execute($testCmd);
        $worker->waitForFinishedJob();

        $this->assertJUnitLogIsValid($testLog);
    }

    public function testTellsWhenItsFree()
    {
        $testLog = '/tmp/test.xml';
        $testCmd = $this->getCommand('passing-tests/TestOfUnits.php', $testLog);
        $worker = new WrapperWorker();
        $worker->start($this->phpunitWrapper);
        $this->assertTrue($worker->isFree());

        $worker->execute($testCmd);
        $this->assertFalse($worker->isFree());

        $worker->waitForFinishedJob();
        $this->assertTrue($worker->isFree());
    }

    public function testTellsWhenItsStopped()
    {
        $worker = new WrapperWorker();
        $this->assertFalse($worker->isRunning());

        $worker->start($this->phpunitWrapper);
        $this->assertTrue($worker->isRunning());

        $worker->stop();
        $worker->waitForStop();
        $this->assertFalse($worker->isRunning());
    }

    public function testProcessIsMarkedAsCrashedWhenItFinishesWithNonZeroExitCode()
    {
        // fake state: process has already exited (with non-zero exit code) but worker did not yet notice
        $worker = new WrapperWorker();
        $this->setPerReflection($worker, 'proc', $this->createSomeClosedProcess());
        $this->setPerReflection($worker, 'pipes', [0 => true]);
        $this->assertTrue($worker->isCrashed());
    }

    private function createSomeClosedProcess()
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open('thisCommandHasAnExitcodeNotEqualZero', $descriptorspec, $pipes, '/tmp');
        $running = true;
        while ($running) {
            $status = proc_get_status($proc);
            $running = $status['running'];
        }

        return $proc;
    }

    private function setPerReflection($instance, $property, $value)
    {
        $reflectionProperty = new \ReflectionProperty(\get_class($instance), $property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($instance, $value);
    }

    public function testCanExecuteMultiplePHPUnitCommands()
    {
        $bin = 'bin/phpunit-wrapper';

        $worker = new WrapperWorker();
        $worker->start($this->phpunitWrapper);

        $testLog = '/tmp/test.xml';
        $testCmd = $this->getCommand('passing-tests/TestOfUnits.php', $testLog);
        $worker->execute($testCmd);

        $testLog2 = '/tmp/test2.xml';
        $testCmd2 = $this->getCommand('failing-tests/UnitTestWithErrorTest.php', $testLog2);
        $worker->execute($testCmd2);

        $worker->stop();
        $worker->waitForStop();

        $this->assertJUnitLogIsValid($testLog);
        $this->assertJUnitLogIsValid($testLog2);
    }

    private function getCommand($testFile, $logFile)
    {
        return sprintf(
            "'%s' '--bootstrap' '%s' '--log-junit' '%s' '%s'",
            'vendor/bin/phpunit',
            $this->bootstrap,
            $logFile,
            $this->fixture($testFile)
        );
    }

    private function assertJUnitLogIsValid($logFile)
    {
        $this->assertFileExists($logFile, "Failed asserting that $logFile exists.");
        $log = new SimpleXMLElement(file_get_contents($logFile));
        $count = \count($log->testsuite->testcase);
        $this->assertGreaterThan(1, $count, 'Not even a test has been executed');
    }
}
