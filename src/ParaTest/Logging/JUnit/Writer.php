<?php namespace ParaTest\Logging\JUnit;

use ParaTest\Logging\LogInterpreter;

class Writer
{
    protected $name;
    protected $outputPath;
    protected $interpreter;

    public function __construct($name, $outputPath, LogInterpreter $interpreter)
    {
        $this->name = $name;
        $this->outputPath = $outputPath;
        $this->interpreter = $interpreter;
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