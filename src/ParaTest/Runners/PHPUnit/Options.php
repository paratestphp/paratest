<?php namespace ParaTest\Runners\PHPUnit;

/**
 * Class Options
 *
 * An object containing all configurable information used
 * to run PHPUnit via ParaTest
 *
 * @package ParaTest\Runners\PHPUnit
 */
class Options
{
    /**
     * The number of processes to run at a time
     *
     * @var int
     */
    protected $processes;

    /**
     * The test path pointing to tests that will
     * be run
     *
     * @var string
     */
    protected $path;

    /**
     * The path to the PHPUnit binary that will be run
     *
     * @var string
     */
    protected $phpunit;

    /**
     * Determines whether or not ParaTest runs in
     * functional mode. If enabled, ParaTest will run
     * every test method in a separate process
     *
     * @var string
     */
    protected $functional;

    /**
     * Prevents starting new tests after a test has failed.
     *
     * @var boolean
     */
    protected $stopOnFailure;

    /**
     * A collection of post-processed option values. This is the collection
     * containing ParaTest specific options
     *
     * @var array
     */
    protected $filtered;

    /**
     * A collection of option values directly corresponding
     * to certain annotations - i.e group
     *
     * @var array
     */
    protected $annotations = array();

    public function __construct($opts = array())
    {
        foreach(self::defaults() as $opt => $value)
            $opts[$opt] = (isset($opts[$opt])) ? $opts[$opt] : $value;

        $this->processes = $opts['processes'];
        $this->path = $opts['path'];
        $this->phpunit = $opts['phpunit'];
        $this->functional = $opts['functional'];
        $this->stopOnFailure = $opts['stop-on-failure'];
        $this->runner = $opts['runner'];
        $this->noTestTokens = $opts['no-test-tokens'];
        $this->colors = $opts['colors'];

        $this->filtered = $this->filterOptions($opts);
        $this->initAnnotations();
    }

    /**
     * Public read accessibility
     *
     * @param $var
     * @return mixed
     */
    public function __get($var)
    {
        return $this->$var;
    }

    /**
     * Returns a collection of ParaTest's default
     * option values
     *
     * @return array
     */
    protected static function defaults()
    {
        return array(
            'processes' => 5,
            'path' => '',
            'phpunit' => static::phpunit(),
            'functional' => false,
            'stop-on-failure' => false,
            'runner' => 'Runner',
            'no-test-tokens' => false,
            'colors' => false,
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
     * @param  array $options
     * @return array
     */
    protected function filterOptions($options)
    {
        $filtered = array_diff_key($options, array(
            'processes' => $this->processes,
            'path' => $this->path,
            'phpunit' => $this->phpunit,
            'functional' => $this->functional,
            'stop-on-failure' => $this->stopOnFailure,
            'runner' => $this->runner,
            'no-test-tokens' => $this->noTestTokens,
            'colors' => $this->colors,
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
