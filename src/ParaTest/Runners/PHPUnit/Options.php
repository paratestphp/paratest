<?php namespace ParaTest\Runners\PHPUnit;

class Options
{
    protected $processes;
    protected $path;
    protected $phpunit;
    protected $filtered;

    public function __construct($opts = array())
    {
        foreach(self::defaults() as $opt => $value)
            $opts[$opt] = (isset($opts[$opt])) ? $opts[$opt] : $value;

        $this->processes = $opts['processes'];
        $this->path = $opts['path'];
        $this->phpunit = $opts['phpunit'];

        $this->filtered = $this->filterOptions($opts);
    }

    public function __get($var)
    {
        return $this->$var;
    }

    protected static function defaults()
    {
        return array(
            'processes' => 5,
            'path' => getcwd(),
            'phpunit' => 'phpunit'
        );
    }

    protected function filterOptions($options)
    {
        return array_diff_key($options, array(
            'processes' => $this->processes,
            'path' => $this->path,
            'phpunit' => $this->phpunit
        ));
    }

}