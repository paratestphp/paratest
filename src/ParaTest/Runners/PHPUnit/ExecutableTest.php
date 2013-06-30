<?php namespace ParaTest\Runners\PHPUnit;

use Symfony\Component\Process\Process;

abstract class ExecutableTest
{
    /**
     * The path to the test to run
     *
     * @var string
     */
    protected $path;

    /**
     * A path to the temp file created
     * for this test
     *
     * @var string
     */
    protected $temp;
    protected $fullyQualifiedClassName;
    protected $pipes = array();

    /**
     * @var Process
     */
    protected $process;

    /**
     * A unique token value for a given
     * process
     *
     * @var int
     */
    protected $token;

    public function __construct($path, $fullyQualifiedClassName = null)
    {
        $this->path = $path;
        $this->fullyQualifiedClassName = $fullyQualifiedClassName;
    }

    /**
     * Get the path to the test being executed
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Returns the path to this test's temp file.
     * If the temp file does not exist, it will be
     * created
     *
     * @return string
     */
    public function getTempFile()
    {
        if(is_null($this->temp))
            $this->temp = tempnam(sys_get_temp_dir(), "PT_");

        return $this->temp;
    }

    /**
     * Return the test process' stderr contents
     *
     * @return string
     */
    public function getStderr()
    {
        return $this->process->getErrorOutput();
    }

    /**
     * Stop the process and return it's
     * exit code
     *
     * @return int
     */
    public function stop()
    {
        return $this->process->stop();
    }

    /**
     * Removes the test file
     */
    public function deleteFile()
    {
        $outputFile = $this->getTempFile();
        unlink($outputFile);
    }

    /**
     * Check if the process has terminated.
     *
     * @return bool
     */
    public function isDoneRunning()
    {
        return $this->process->isTerminated();
    }

    /**
     * Return the exit code of the process
     *
     * @return int
     */
    public function getExitCode()
    {
        return $this->process->getExitCode();
    }

    /**
     * Executes the test by creating a separate process
     *
     * @param $binary
     * @param array $options
     * @param array $environmentVariables
     * @return $this
     */
    public function run($binary, $options = array(), $environmentVariables = array())
    {
        $this->handleEnvironmentVariables($environmentVariables);
        $command = $this->command($binary, $options);
        $this->process = new Process($command, null, $environmentVariables);
        $this->process->start();
        return $this;
    }

    /**
     * Returns the unique token for this test process
     *
     * @return int
     */
    public function getToken()
    {
        return $this->token;
    }

    public function command($binary, $options = array())
    {
        $options = array_merge($this->prepareOptions($options), array('log-junit' => $this->getTempFile()));
        return $this->getCommandString($binary, $options);
    }

    /**
     * A template method that can be overridden to add necessary options for a test
     *
     * @param  array $options the options that are passed to the run method
     * @return array $options the prepared options
     */
    protected function prepareOptions($options)
    {
        return $options;
    }

    /**
     * Returns the command string that will be executed
     * by proc_open
     *
     * @param $binary
     * @param array $options
     * @return mixed
     */
    protected function getCommandString($binary, $options = array(), $environmentVariables = array())
    {
        // TODO: this should use a CommandBuilder
        //Identify paratest as the test runner
        $environmentVariablePrefix = 'PARATEST=1 ';
        $command = $binary;
        foreach($options as $key => $value) $command .= " --$key %s";
        foreach($environmentVariables as $key => $value) $environmentVariablePrefix .= "$key=%s ";
        $args = array_merge(array("$environmentVariablePrefix$command %s %s"), array_values($environmentVariables), array_values($options), array($this->fullyQualifiedClassName, $this->getPath()));
        $command = call_user_func_array('sprintf', $args);
        return $command;
    }

    /**
     * Checks environment variables for the presence of a TEST_TOKEN
     * variable and sets $this->token based on its value
     *
     * @param $environmentVariables
     */
    protected function handleEnvironmentVariables($environmentVariables)
    {
        if (isset($environmentVariables['TEST_TOKEN'])) $this->token = $environmentVariables['TEST_TOKEN'];
    }
}
