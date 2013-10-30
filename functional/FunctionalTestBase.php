<?php

use \Habitat\Habitat;

class FunctionalTestBase extends PHPUnit_Framework_TestCase
{
    protected $bootstrap;
    protected $path;
    protected $exitCode = -1;
    private   $errorOutput;

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

    protected function setErrorOutput($errorOutput)
    {
        return $this->errorOutput = $errorOutput;
    }

    protected function getErrorOutput()
    {
        return $this->errorOutput;
    }

    protected function getOption($switch, $value) {
        if(strlen($switch) > 1) $switch = '--' . $switch;
        else $switch = '-' . $switch;

        return $value ? $switch . ' ' . $value : $switch;
    }

    protected function getTestOutput($cmd)
    {
        $proc = $this->getFinishedProc($cmd, $pipes);
        $output = $proc->getOutput();
        $this->setErrorOutput($proc->getErrorOutput());

        return $output;
    }

    protected function getFinishedProc($cmd, &$pipes)
    {
        $env = defined('PHP_WINDOWS_VERSION_BUILD') ? Habitat::getAll() : null;
        $this->lastExecutedCommand = $cmd;
        $proc = new \Symfony\Component\Process\Process($cmd, null, $env, null, $timeout = 600);
        $this->waitForProc($proc);

        return $proc;
    }

    protected function waitForProc(\Symfony\Component\Process\Process $proc)
    {
        $proc->run();
        $this->exitCode = $proc->getExitCode();
    }

    protected function getExitCode()
    {
        return $this->exitCode;
    }

    protected function normalizeStr($string)
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            return str_replace("\r\n", "\n", $string);
        }
        return $string;
    }

    protected function createSmallTests($number)
    {
        exec("php {$this->path}/generate.php $number", $output);
    }

    protected function deleteSmallTests()
    {
        foreach (glob(FIXTURES . '/small-tests/FastUnit*Test.php') as $generatedFile) {
            unlink($generatedFile);
        }
    }

    protected function debugInformation()
    {
        return "The last executed command was `{$this->lastExecutedCommand}`.";
    }
}
