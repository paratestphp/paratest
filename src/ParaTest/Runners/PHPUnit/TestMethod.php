<?php namespace ParaTest\Runners\PHPUnit;

class TestMethod
{
    protected $path;
    protected $name;

    public function __construct($suitePath, $name)
    {
        $this->path = $suitePath;
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }
}