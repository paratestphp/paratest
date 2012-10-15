<?php namespace ParaTest\Runners\PHPUnit;

class Suite
{
    private $path;
    private $functions;
    private $temp;
    private $pipes;
    private $resource;

    public function __construct($path, $functions)
    {
        $this->path = $path;
        $this->functions = $functions;
        $this->pipes = array();
        $this->resource = null;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getFunctions()
    {
        return $this->functions;
    }

    public function getTempFile()
    {
        if(is_null($this->temp))
            $this->temp = tempnam('/tmp/paratest', sprintf("%s.xml", basename($this->path)));
        return $this->temp;
    }

    public function isDone()
    {
        if(is_null($this->resource)) return false;
    }
}