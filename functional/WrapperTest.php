<?php

class WrapperTest extends FunctionalTestBase
{
    public function setUp()
    {
        parent::setUp();
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
        $bin = 'bin/phpunit-wrapper';
        $pipes = array();
        $proc = proc_open($bin, self::$descriptorspec, $pipes); 
        fwrite($pipes[0], $testCmd . "\n");
        fwrite($pipes[0], "EXIT\n");
        fclose($pipes[0]);

        $this->waitForProc($proc);

        $this->assertJUnitLogIsValid($testLog);
    }

    public function testCanExecuteMultiplePHPUnitCommands()
    {
        $bin = 'bin/phpunit-wrapper';
        $pipes = array();
        $proc = proc_open($bin, self::$descriptorspec, $pipes); 

        $testLog = '/tmp/test.xml';
        $testCmd = $this->getCommand('TestOfUnits.php', $testLog);
        fwrite($pipes[0], $testCmd . "\n");

        $testLog2 = '/tmp/test2.xml';
        $testCmd = $this->getCommand('UnitTestWithErrorTest.php', $testLog2);
        fwrite($pipes[0], $testCmd . "\n");

        fwrite($pipes[0], "EXIT\n");
        fclose($pipes[0]);

        $this->waitForProc($proc);

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
