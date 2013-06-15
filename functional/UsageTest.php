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

    public function testCallingParaTestWithShortHelpOptionDisplaysUsage()
    {
        $output = $this->getParaTestOutput(false, '-h');
        $this->assertEquals($this->normalizeStr($this->usage), $this->normalizeStr($output));
    }

    public function testCallingParaTestWithLongHelpOptionDisplaysUsage()
    {
        $output = $this->getParaTestOutput(false, '--help');
        $this->assertEquals($this->normalizeStr($this->usage), $this->normalizeStr($output));
    }

    protected function getParaTestOutput($functional = false, $options = '')
    {
        $proc = new \Symfony\Component\Process\Process(PARA_BINARY . ' ' . $options);
        $this->waitForProc($proc);
        $output = $proc->getOutput();

        return $output;
    }
}
