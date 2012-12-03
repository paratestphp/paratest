<?php

class FunctionalTestBase extends PHPUnit_Framework_TestCase
{
    protected $bootstrap;
    protected $path;
    protected $exitCode = -1;

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
        $cmd = sprintf("%s --bootstrap %s %s", PHPUNIT, $this->bootstrap, $this->path);
        return $this->getTestOutput($cmd);
    }

    protected function getParaTestOutput($functional = false, $options = array())
    {
        $cmd = sprintf("%s --bootstrap %s --phpunit %s", PARA_BINARY, $this->bootstrap, PHPUNIT);
        if($functional) $cmd .= ' --functional';
        foreach($options as $switch => $value)
            $cmd .= sprintf(" %s", 
                           $this->getOption($switch, $value));
        $cmd .= sprintf(" %s", $this->path);
        return $this->getTestOutput($cmd);
    }

    protected function getOption($switch, $value) {
        if(strlen($switch) > 1) $switch = '--' . $switch;
        else $switch = '-' . $switch;
        return $value ? $switch . ' ' . $value : $switch;
    }

    protected function getTestOutput($cmd)
    {
        $proc = $this->getFinishedProc($cmd, $pipes);
        $output = $this->getOutput($pipes);
        return $output;
    }

    protected function getFinishedProc($cmd, &$pipes)
    {
        $pipes = array();
        $proc = proc_open($cmd, self::$descriptorspec, $pipes); 
        $this->waitForProc($proc);
        return $proc;
    }

    protected function getOutput($pipes)
    {
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        return $output;
    }

    protected function waitForProc($proc)
    {
        $status = proc_get_status($proc);
        while($status['running']) {
            $status = proc_get_status($proc);
            $this->exitCode = $status['exitcode'];
        }
    }

    protected function getExitCode()
    {
        return $this->exitCode;
    }
}