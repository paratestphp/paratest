<?php

class UsageTest extends \FunctionalTestBase
{
    protected $usage;

    public function setUp()
    {
        parent::__construct();
        $file = FIXTURES . DS . 'output' . DS . 'usage.txt';
        $this->usage = file_get_contents($file);
    }
}