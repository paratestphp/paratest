<?php namespace ParaTest\Runners\PHPUnit;

class Suite
{
    private $path;
    private $functions;

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
}