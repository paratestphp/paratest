<?php namespace ParaTest\Runners\PHPUnit;

abstract class ExecutableTest
{
    protected $path;
    protected $pipes = array();
    protected $temp;
    protected $process;

    protected static $descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w')
    );

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getPipes()
    {
        return $this->pipes;
    }

    public function getTempFile()
    {
        if(is_null($this->temp))
            $this->temp = tempnam(sys_get_temp_dir(), "PT_");
        return $this->temp;
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

    public function run($binary, $options = array())
    {
        $options = array_merge($this->prepareOptions($options), array('log-junit' => $this->getTempFile()));
        $command = $this->getCommandString($binary, $options);
        $this->process = proc_open($command, self::$descriptors, $this->pipes);
        return $this;
    }

    /**
     * A template method that can be overridden to add necessary options for a test
     * @param array $options the options that are passed to the run method
     * @return array $options the prepared options
     */
    protected function prepareOptions($options)
    {
        return $options;
    }

    protected function getCommandString($binary, $options = array())
    {
        $command = $binary;
        foreach($options as $key => $value) $command .= " --$key %s";
        $args = array_merge(array("$command %s"), array_values($options), array($this->getPath()));
        $command = call_user_func_array('sprintf', $args);
        return $command;
    }
}