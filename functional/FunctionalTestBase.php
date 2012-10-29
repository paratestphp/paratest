<?php

class FunctionalTestBase extends PHPUnit_Framework_TestCase
{
    protected $bootstrap;
    protected $path;
    protected static $descriptorspec = array(
       0 => array("pipe", "r"),
       1 => array("pipe", "w"),
       2 => array("pipe", "w")
    );

    public function setUp()
    {
        $this->path = FIXTURES . DS . 'tests';
        $this->bootstrap = dirname(FIXTURES) . DS . 'bootstrap.php';
    }

    protected function getPhpunitOutput()
    {
        $cmd = sprintf("phpunit --bootstrap %s %s", $this->bootstrap, $this->path);
        return $this->getTestOutput($cmd);
    }

    protected function getParaTestOutput($functional = false)
    {
        $cmd = sprintf("%s --bootstrap %s", PARA_BINARY, $this->bootstrap);
        if($functional) $cmd .= ' --functional';
        $cmd .= sprintf(" --path %s", $this->path);
        return $this->getTestOutput($cmd);
    }

    protected function getTestOutput($cmd)
    {
        $proc = proc_open($cmd, self::$descriptorspec, $pipes); 
        $this->waitForProc($proc);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($proc);
        return $output;
    }

    protected function waitForProc($proc)
    {
        $status = proc_get_status($proc);
        while($status['running'])
            $status = proc_get_status($proc);
    }
}