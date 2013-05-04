<?php
namespace Paratest\Runners\PHPUnit;

class CommandBuilder
{
    private $binary;
    private $environmentVariables;
    private $options;
    private $path;

    /**
     * @param string
     * @return self
     */
    public static function fromBinary($binary)
    {
        $builder = new self();
        return $builder->withBinary($binary);
    }

    /**
     * @param string
     * @return self
     */
    public function withBinary($binary)
    {
        $this->binary = $binary;
        return $this;
    }

    /**
     * @return self
     */
    public function withEnvironmentVariables(array $environmentVariables)
    {
        $this->environmentVariables = $environmentVariables;
        return $this;
    }

    /**
     * @return self
     */
    public function withOptions(array $options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @param string
     * @return self
     */
    public function withPath($path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return string
     */
    public function build()
    {
        $environmentVariablePrefix = '';
        foreach($this->environmentVariables as $key => $value) {
            $environmentVariablePrefix .= "$key=%s ";
        }

        $command = $this->binary;
        foreach($this->options as $key => $value) {
            $command .= " --$key %s";
        }

        $args = array_merge(
            array("$environmentVariablePrefix$command %s"),
            array_values($this->environmentVariables),
            array_values($this->options), 
            array($this->path)
        );
        return call_user_func_array('sprintf', $args);
    }
}
