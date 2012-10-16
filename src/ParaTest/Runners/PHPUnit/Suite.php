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

    public function run()
    {
        $command = sprintf("phpunit --log-junit %s %s", $this->getTempFile(), $this->getPath());
        $this->process = proc_open($command, self::$descriptors, $this->pipes);
        return $this;
    }

    public function stop()
    {
        $outputFile = $this->getTempFile();
        unlink($outputFile);
        return proc_close($this->process);
    }

    public function isDoneRunning()
    {
        $status = proc_get_status($this->process);
        return !$status['running'];
    }
}