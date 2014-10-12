<?php

use \Habitat\Habitat;
use \Symfony\Component\Process\Process;

class ParaTestInvoker
{
    public
        $path,
        $bootstrap;

    public function __construct($path, $bootstrap)
    {
        $this->path = $path;
        $this->bootstrap = $bootstrap;
    }

    /**
     * Runs the command, returns the proc after it's done
     * @return \Symfony\Component\Process\Process
     */
    public function execute($options=array())
    {
        $cmd = $this->buildCommand($options);
        $env = defined('PHP_WINDOWS_VERSION_BUILD') ? Habitat::getAll() : null;
        $proc = new Process($cmd, null, $env, null, $timeout = 600);
        $proc->run();
        return $proc;
    }

    private function buildCommand($options=array())
    {
        $cmd = sprintf("%s --bootstrap %s --phpunit %s", PARA_BINARY, $this->bootstrap, PHPUNIT);
        foreach($options as $switch => $value) {
            if(is_numeric($switch)) {
                $switch = $value;
                $value = null;
            }
            if(strlen($switch) > 1) {
                $switch = '--' . $switch;
            } else {
                $switch = '-' . $switch;
            }
            $cmd .= sprintf(" %s", $value ? $switch . ' ' . $value : $switch);
        }
        return $cmd .= sprintf(" %s", $this->path);
    }
}
