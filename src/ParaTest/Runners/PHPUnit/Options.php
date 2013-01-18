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
            'path' => '',
            'phpunit' => static::phpunit(),
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
        $vendor  = static::vendorDir();
        $phpunit = $vendor . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit';
        $batch = $phpunit . '.bat';
        if(file_exists($batch)) return $batch;
        if(file_exists($phpunit)) return $phpunit;
        return 'phpunit';
    }

    /**
     * Get the path to the vendor directory
     * First assumes vendor directory is accessible from src (i.e development)
     * Second assumes vendor directory is accessible within src
     */
    protected static function vendorDir()
    {
        $vendor = dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'vendor';
        if(!file_exists($vendor))
            $vendor = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))));
        return $vendor;
    }

    /**
     * Filter options to distinguish between paratest
     * internal options and any other options
     * @param array $options
     * @return array
     */
    protected function filterOptions($options)
    {
        $filtered = array_diff_key($options, array(
            'processes' => $this->processes,
            'path' => $this->path,
            'phpunit' => $this->phpunit,
            'functional' => $this->functional
        ));
        if($configuration = $this->getConfigurationPath($filtered))
            $filtered['configuration'] = new Configuration($configuration);
        return $filtered;
    }

    /**
     * Take an array of filtered options and return a
     * configuration path
     *
     * @param $filtered
     * @return string|null
     */
    protected function getConfigurationPath($filtered)
    {
        if(isset($filtered['configuration']))
            return file_exists($filtered['configuration']) ? realpath($filtered['configuration']) : $filtered['configuration'];
        if(file_exists('phpunit.xml'))
            return realpath('phpunit.xml');
        if(file_exists('phpunit.xml.dist'))
            return realpath('phpunit.xml.dist');
    }

    /**
     * Load options that are represented by annotations
     * inside of tests i.e @group group1 = --group group1
     */
    protected function initAnnotations()
    {
        $annotatedOptions = array('group');
        foreach($this->filtered as $key => $value)
            if(array_search($key, $annotatedOptions) !== false)
                $this->annotations[$key] = $value;
    }
}