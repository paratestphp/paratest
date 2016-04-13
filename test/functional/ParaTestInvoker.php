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
     *
     * @param array $options
     * @param callable  $callback
     *
     * @return Process
     */
    public function execute($options=array(), $callback = null)
    {
        $cmd = $this->buildCommand($options);
        $env = defined('PHP_WINDOWS_VERSION_BUILD') ? Habitat::getAll() : null;
        $proc = new Process($cmd, null, $env, null, $timeout = 600);

        if (!is_callable($callback)) {
            $proc->run();
        } else {
            $proc->run($callback);
        }

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
        $cmd .= sprintf(" %s", $this->path);

        return $cmd;
    }
}
