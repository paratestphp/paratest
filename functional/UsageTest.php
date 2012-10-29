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
        $proc = proc_open(PARA_BINARY, FunctionalTestBase::$descriptorspec, $pipes);
        $this->waitForProc($proc);
        $output = $this->getOutput($pipes);
        proc_close($proc);
        $this->assertEquals($this->usage, $output);
    }
}