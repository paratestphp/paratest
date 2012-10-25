<?php namespace ParaTest\Runners\PHPUnit;

class Suite extends ExecutableTest
{
    private $functions;

    public function __construct($path, $functions)
    {
        parent::__construct($path);
        $this->functions = $functions;
    }

    public function getFunctions()
    {
        return $this->functions;
    }
}