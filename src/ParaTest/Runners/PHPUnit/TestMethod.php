<?php namespace ParaTest\Runners\PHPUnit;

class TestMethod extends ExecutableTest
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

    protected function prepareOptions($options)
    {
        $options['filter'] = $this->name;
        return $options;
    }
}