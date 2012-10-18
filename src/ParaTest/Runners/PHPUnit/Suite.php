<?php namespace ParaTest\Runners\PHPUnit;

class Suite
{
    private $path;
    private $functions;
    private $temp;
    private $pipes = array();
    private $process;

    private static $descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w')
    );

    public function __construct($path, $functions)
    {
        $this->path = $path;
        $this->functions = $functions;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getFunctions()
    {
        return $this->functions;
    }

    public function getPipes()
    {
        return $this->pipes;
    }

    public function getTempFile()
    {
        if(is_null($this->temp))
            $this->temp = tempnam(sys_get_temp_dir(), sprintf("%s.xml", basename($this->path)));
        return $this->temp;
    }

    public function run($binary, $options = array())
    {
        $options = array_merge($options, array('log-junit' => $this->getTempFile()));
        $command = $this->getCommandString($binary, $options);
        $this->process = proc_open($command, self::$descriptors, $this->pipes);
        return $this;
    }

    public function stop()
    {
        return proc_close($this->process);
    }

    public function deleteFile()
    {
        $outputFile = $this->getTempFile();
        unlink($outputFile);
    }

    public function isDoneRunning()
    {
        $status = proc_get_status($this->process);
        return !$status['running'];
    }

    private function getCommandString($binary, $options = array())
    {
        $command = $binary;
        foreach($options as $key => $value) $command .= " --$key %s";
        $args = array_merge(array("$command %s"), array_values($options), array($this->getPath()));
        $command = call_user_func_array('sprintf', $args);
        return $command;
    }
}