<?php namespace ParaTest\Runners\PHPUnit;

class Runner
{
    protected $maxProcs;
    
    public function __construct($maxProcs = 5)
    {
        $this->maxProcs = $maxProcs;
    }
}