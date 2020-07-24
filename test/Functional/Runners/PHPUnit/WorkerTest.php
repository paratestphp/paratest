<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Worker\WrapperWorker;
use ParaTest\Tests\TestBase;
use ReflectionProperty;
use SimpleXMLElement;

use function count;
use function file_exists;
use function file_get_contents;
use function get_class;
use function proc_get_status;
use function proc_open;
use function sys_get_temp_dir;
use function unlink;

class WorkerTest extends TestBase
{
    protected static $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    /** @var string */
    protected $bootstrap;
    /** @var string */
    private $phpunitWrapper;

    public function setUp(): void
    {
        parent::setUp();
        $this->bootstrap      = PARATEST_ROOT . DS . 'test' . DS . 'bootstrap.php';
        $this->phpunitWrapper = PARATEST_ROOT . DS . 'bin' . DS . 'phpunit-wrapper.php';
    }

    public function tearDown(): void
    {
        $this->deleteIfExists(sys_get_temp_dir() . DS . 'test.xml');
        $this->deleteIfExists(sys_get_temp_dir() . DS . 'test2.xml');
    }

    private function deleteIfExists($file): void
    {
        if (! file_exists($file)) {
            return;
        }

        unlink($file);
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testReadsAPHPUnitCommandFromStdInAndExecutesItItsOwnProcess(): void
    {
        $testLog = sys_get_temp_dir() . DS . 'test.xml';
        $testCmd = $this->getCommand('passing-tests' . DS . 'TestOfUnits.php', $testLog);
        $worker  = new WrapperWorker();
        $worker->start($this->phpunitWrapper);
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
        $testLog = sys_get_temp_dir() . DS . 'test.xml';
        $testCmd = $this->getCommand('passing-tests' . DS . 'TestOfUnits.php', $testLog);
        $worker  = new WrapperWorker();
        $worker->start($this->phpunitWrapper);
        $worker->execute($testCmd);
        $worker->waitForFinishedJob();

        $this->assertJUnitLogIsValid($testLog);
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testTellsWhenItsFree(): void
    {
        $testLog = sys_get_temp_dir() . DS . 'test.xml';
        $testCmd = $this->getCommand('passing-tests' . DS . 'TestOfUnits.php', $testLog);
        $worker  = new WrapperWorker();
        $worker->start($this->phpunitWrapper);
        $this->assertTrue($worker->isFree());

        $worker->execute($testCmd);
        $this->assertFalse($worker->isFree());

        $worker->waitForFinishedJob();
        $this->assertTrue($worker->isFree());
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testTellsWhenItsStopped(): void
    {
        $worker = new WrapperWorker();
        $this->assertFalse($worker->isRunning());

        $worker->start($this->phpunitWrapper);
        $this->assertTrue($worker->isRunning());

        $worker->stop();
        $worker->waitForStop();
        $this->assertFalse($worker->isRunning());
    }

    /**
     * @requires OSFAMILY Linux
     */
    public function testProcessIsMarkedAsCrashedWhenItFinishesWithNonZeroExitCode(): void
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

        $proc    = proc_open('thisCommandHasAnExitcodeNotEqualZero', $descriptorspec, $pipes, '/tmp');
        $running = true;
        while ($running) {
            $status  = proc_get_status($proc);
            $running = $status['running'];
        }

        return $proc;
    }

    private function setPerReflection($instance, $property, $value): void
    {
        $reflectionProperty = new ReflectionProperty(get_class($instance), $property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($instance, $value);
    }

    public function testCanExecuteMultiplePHPUnitCommands(): void
    {
        $bin = 'bin/phpunit-wrapper.php';

        $worker = new WrapperWorker();
        $worker->start($this->phpunitWrapper);

        $testLog = sys_get_temp_dir() . DS . 'test.xml';
        $testCmd = $this->getCommand('passing-tests' . DS . 'TestOfUnits.php', $testLog);
        $worker->execute($testCmd);

        $testLog2 = sys_get_temp_dir() . DS . 'test2.xml';
        $testCmd2 = $this->getCommand('failing-tests' . DS . 'UnitTestWithErrorTest.php', $testLog2);
        $worker->execute($testCmd2);

        $worker->stop();
        $worker->waitForStop();

        $this->assertJUnitLogIsValid($testLog);
        $this->assertJUnitLogIsValid($testLog2);
    }

    private function getCommand($testFile, $logFile)
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

    private function assertJUnitLogIsValid($logFile): void
    {
        $this->assertFileExists($logFile);
        $log   = new SimpleXMLElement(file_get_contents($logFile));
        $count = count($log->testsuite->testcase);
        $this->assertGreaterThan(1, $count, 'Not even a test has been executed');
    }
}
