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
            'phpunit' => self::phpunit(),
            'functional' => false
        );
    }

    /**
     * Get the path to phpunit
     * First checks if a Windows batch script is in the composer vendors directory.
     * Composer automatically handles creating a .bat file, so if on windows this should be the case.
     * Second look for the phpunit binary under nix
     * Defaults to phpunit on the users PATH
     * @return string $phpunit the path to phpunit
     */
    protected static function phpunit()
    {
        $phpunit  = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))));
        $phpunit .= DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit';
        $batch = $phpunit . '.bat';
        if(file_exists($batch)) return $batch;
        if(file_exists($phpunit)) return $phpunit;
        return 'phpunit';
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