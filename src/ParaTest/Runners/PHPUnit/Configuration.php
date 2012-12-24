<?php namespace ParaTest\Runners\PHPUnit;

class Configuration
{
    protected $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function getPath()
    {
        return $this->path;
    }
}
