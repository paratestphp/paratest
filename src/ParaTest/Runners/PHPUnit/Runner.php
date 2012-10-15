<?php namespace ParaTest\Runners\PHPUnit;

class Runner
{
    protected $maxProcs;
    protected $suite;
    
    public function __construct($opts = array())
    {
        $opts = array_merge(self::defaults(), $opts);
        $this->maxProcs = $opts['maxProcs'];
        $this->suite = $opts['suite'];
    }

    private static function defaults()
    {
        return array(
            'maxProcs' => 5,
            'suite' => getcwd()
        );
    }
}