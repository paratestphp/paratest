<?php namespace ParaTest\Runners\PHPUnit;

abstract class ExecutableTest
{
    protected $path;
    protected $pipes = array();
    protected $temp;
    protected $process;
    protected $status;
    protected $stderr;
    protected $token;

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

    public function getStderr()
    {
        return $this->stderr;
    }

    public function stop()
    {
        $this->initStreams();
        return proc_close($this->process);
    }

    public function deleteFile()
    {
        $outputFile = $this->getTempFile();
        unlink($outputFile);
    }

    /**
     * Weather or not the process has finished running
     * This function updates the member variable $status
     * for such cases when the status must be cached, i.e
     * when the exit code must be fetched, but subsequent
     * calls would overwrite the exit code with a meaningless
     * code.
     */
    public function isDoneRunning()
    {
        $this->status = proc_get_status($this->process);
        return !$this->status['running'];
    }

    /**
     * Called after a polling context to retrieve
     * the exit code of the phpunit process
     */
    public function getExitCode()
    {
        return $this->status['exitcode'];
    }

    public function run($binary, $options = array(), $environmentVariables = array())
    {
        $options = array_merge($this->prepareOptions($options), array('log-junit' => $this->getTempFile()));
        $this->handleEnvironmentVariables($environmentVariables);
        $command = $this->getCommandString($binary, $options, $environmentVariables);
        $this->process = proc_open($command, self::$descriptors, $this->pipes);
        return $this;
    }

    protected function initStreams()
    {
        $pipes = $this->getPipes();
        $this->stderr = stream_get_contents($pipes[2]);
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

    protected function getCommandString($binary, $options = array(), $environmentVariables = array())
    {
        $command = $binary;
        $environmentVariablePrefix = '';

        foreach($options as $key => $value) $command .= " --$key %s";
        foreach($environmentVariables as $key => $value) $environmentVariablePrefix .= "$key=%s ";
        $args = array_merge(array("$environmentVariablePrefix$command %s"), array_values($environmentVariables), array_values($options), array($this->getPath()));
        $command = call_user_func_array('sprintf', $args);
        return $command;
    }

    public function getToken()
    {
        return $this->token;
    }

    protected function handleEnvironmentVariables($environmentVariables)
    {
        if (isset($environmentVariables['TEST_TOKEN'])) $this->token = $environmentVariables['TEST_TOKEN'];
    }
}