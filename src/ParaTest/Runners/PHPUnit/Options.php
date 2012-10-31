<?php namespace ParaTest\Runners\PHPUnit;

class Options
{
    protected $processes;
    protected $path;
    protected $phpunit;
    protected $functional;
    protected $filtered;
    protected $annotations = array();

    public function __construct($opts = array())
    {
        foreach(self::defaults() as $opt => $value)
            $opts[$opt] = (isset($opts[$opt])) ? $opts[$opt] : $value;

        $this->processes = $opts['processes'];
        $this->path = $opts['path'];
        $this->phpunit = $opts['phpunit'];
        $this->functional = $opts['functional'];

        $this->filtered = $this->filterOptions($opts);
        $this->initAnnotations();
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
            'phpunit' => 'phpunit',
            'functional' => false
        );
    }

    protected function filterOptions($options)
    {
        return array_diff_key($options, array(
            'processes' => $this->processes,
            'path' => $this->path,
            'phpunit' => $this->phpunit,
            'functional' => $this->functional
        ));
    }

    /**
     * Load options that are represented by annotations
     * inside of tests i.e @grup group1 = --group group1
     */
    protected function initAnnotations()
    {
        $annotatedOptions = array('group');
        foreach($this->filtered as $key => $value)
            if(array_search($key, $annotatedOptions) !== false)
                $this->annotations[$key] = $value;
    }
}