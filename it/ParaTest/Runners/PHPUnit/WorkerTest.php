<?php
namespace ParaTest\Runners\PHPUnit;
use SimpleXMLElement;

class WorkerTest extends \TestBase
{
    public function setUp()
    {
        parent::setUp();
        $this->path = FIXTURES . DS . 'tests';
        $this->bootstrap = dirname(FIXTURES) . DS . 'bootstrap.php';
        $this->phpunitWrapper = PARATEST_ROOT . '/bin/phpunit-wrapper';
    }

    public function tearDown()
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
        $testCmd = $this->getCommand('TestOfUnits.php', $testLog);
        $worker = new Worker();
        $worker->start($this->phpunitWrapper);
        $worker->execute($testCmd);

        $worker->stop();
        $worker->waitForStop();

        $this->assertJUnitLogIsValid($testLog);
    }

    public function testKnowsWhenAJobIsFinished()
    {
        $testLog = '/tmp/test.xml';
        $testCmd = $this->getCommand('TestOfUnits.php', $testLog);
        $worker = new Worker();
        $worker->start($this->phpunitWrapper);
        $worker->execute($testCmd);
        $worker->waitForFinishedJob();

        $this->assertJUnitLogIsValid($testLog);
    }

    public function testTellsWhenItsFree()
    {
        $testLog = '/tmp/test.xml';
        $testCmd = $this->getCommand('TestOfUnits.php', $testLog);
        $worker = new Worker();
        $worker->start($this->phpunitWrapper);
        $this->assertTrue($worker->isFree());

        $worker->execute($testCmd);
        $this->assertFalse($worker->isFree());

        $worker->waitForFinishedJob();
        $this->assertTrue($worker->isFree());
    }

    public function testTellsWhenItsStopped()
    {
        $worker = new Worker();
        $this->assertFalse($worker->isRunning());

        $worker->start($this->phpunitWrapper);
        $this->assertTrue($worker->isRunning());

        $worker->stop();
        $worker->waitForStop();
        $this->assertFalse($worker->isRunning());
    }

    public function testCanExecuteMultiplePHPUnitCommands()
    {
        $bin = 'bin/phpunit-wrapper';

        $worker = new Worker();
        $worker->start($this->phpunitWrapper);

        $testLog = '/tmp/test.xml';
        $testCmd = $this->getCommand('TestOfUnits.php', $testLog);
        $worker->execute($testCmd);

        $testLog2 = '/tmp/test2.xml';
        $testCmd2 = $this->getCommand('UnitTestWithErrorTest.php', $testLog2);
        $worker->execute($testCmd2);

        $worker->stop();
        $worker->waitForStop();

        $this->assertJUnitLogIsValid($testLog);
        $this->assertJUnitLogIsValid($testLog2);
    }

    protected static $descriptorspec = array(
       0 => array("pipe", "r"),
       1 => array("pipe", "w"),
       2 => array("pipe", "w")
    );

    private function getCommand($test, $logFile)
    {
        return sprintf(
            "%s --bootstrap %s --log-junit %s %s",
            'vendor/bin/phpunit', 
            $this->bootstrap, 
            $logFile, 
            $this->path . '/' . $test
        );
    }

    private function assertJUnitLogIsValid($logFile)
    {
        $this->assertTrue(file_exists($logFile), "Failed asserting that $logFile exists.");
        $log = new SimpleXMLElement(file_get_contents($logFile));
        $count = count($log->testsuite->testcase);
        $this->assertTrue($count > 1, "Not even a test has been executed");
    }
}
