<?php namespace ParaTest\Logging\JUnit;

class Writer
{
    protected $name;
    protected $outputPath;

    public function __construct($name, $outputPath)
    {
        $this->name = $name;
        $this->outputPath = $outputPath;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getOutputPath()
    {
        return $this->outputPath;
    }
}