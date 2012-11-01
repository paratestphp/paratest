<?php

class UsageTest extends FunctionalTestBase
{
    protected $usage;

    public function setUp()
    {
        parent::__construct();
        $file = FIXTURES . DS . 'output' . DS . 'usage.txt';
        $this->usage = file_get_contents($file);
    }

    public function testCallingParaTestWithNoArgsDisplaysUsage()
    {
        $output = $this->getParaTestOutput();
        $this->assertEquals($this->usage, $output);
    }

    public function testCallingParaTestWithShortHelpOptionDisplaysUsage()
    {
        $output = $this->getParaTestOutput(false, '-h');   
        $this->assertEquals($this->usage, $output);
    }

    public function testCallingParaTestWithLongHelpOptionDisplaysUsage()
    {
        $output = $this->getParaTestOutput(false, '--help');
        $this->assertEquals($this->usage, $output);
    }

    protected function getParaTestOutput($functional = false, $options = '')
    {
        $proc = proc_open(PARA_BINARY . ' ' . $options, FunctionalTestBase::$descriptorspec, $pipes);
        $this->waitForProc($proc);
        $output = $this->getOutput($pipes);
        proc_close($proc);
        return $output;
    }
}